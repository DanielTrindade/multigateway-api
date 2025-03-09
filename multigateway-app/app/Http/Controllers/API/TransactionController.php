<?php

namespace App\Http\Controllers\API;

use App\Models\Client;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        $transactions = Transaction::with(['client', 'gateway', 'products'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Opcionalmente, cache o resultado paginado
        $page = request()->input('page', 1);
        $cacheKey = "transactions_page_{$page}";

        $transactions = Cache::store('redis')->remember($cacheKey, 5 * 60, function () {
            return Transaction::with(['client', 'gateway', 'products'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        });

        return TransactionResource::collection($transactions);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['client', 'gateway', 'products']);
        return new TransactionResource($transaction);
    }

    public function purchase(Request $request)
    {
        $startTime = microtime(true);

        $validatedData = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1|max:100',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email:rfc,dns|max:255',
            'card_number' => [
                'required',
                'string',
                'size:16',
                'regex:/^[0-9]+$/'
            ],
            'card_cvv' => 'required|string|size:3|regex:/^[0-9]+$/',
        ]);

        // Carregar todos os produtos de uma única vez
        $productIds = array_column($validatedData['products'], 'id');
        $productsCollection = Product::findMany($productIds);

        // Calcular o total
        $total = 0;
        $productItems = [];

        foreach ($validatedData['products'] as $item) {
            $product = $productsCollection->firstWhere('id', $item['id']);
            $total += $product->amount * $item['quantity'];
            $productItems[] = [
                'id' => $product->id,
                'quantity' => $item['quantity'],
            ];
        }

        // Verificar ou criar cliente com cache
        $clientEmail = $validatedData['client_email'];
        $client = Cache::store('redis')->remember(
            'client:email:' . md5($clientEmail),
            3600,
            function () use ($clientEmail, $validatedData) {
                return Client::firstOrCreate(
                    ['email' => $clientEmail],
                    ['name' => $validatedData['client_name']]
                );
            }
        );

        // Processar pagamento
        $paymentResponse = $this->paymentService->processPayment([
            'amount' => $total,
            'name' => $client->name,
            'email' => $client->email,
            'card_number' => $validatedData['card_number'],
            'cvv' => $validatedData['card_cvv'],
        ]);

        if (!$paymentResponse['success']) {
            // Log de falha no processamento
            Log::channel('transactions')->warning('Payment processing failed', [
                'client_id' => $client->id,
                'amount' => $total,
                'errors' => $paymentResponse['errors'],
                'processing_time_ms' => $paymentResponse['processing_time_ms'] ?? null,
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Falha no processamento do pagamento',
                'errors' => $paymentResponse['errors']
            ], 422);
        }

        // Criar transação
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => $paymentResponse['gateway_id'],
            'external_id' => $paymentResponse['external_id'],
            'status' => 'COMPLETED',
            'amount' => $total,
            'card_last_numbers' => substr($validatedData['card_number'], -4),
        ]);

        // Adicionar produtos à transação em um único comando attach
        $attachData = [];
        foreach ($productItems as $item) {
            $attachData[$item['id']] = ['quantity' => $item['quantity']];
        }
        $transaction->products()->attach($attachData);

        // Carregar todas as relações necessárias em uma ÚNICA chamada
        $transaction->load(['client', 'gateway', 'products']);

        // Calcular o tempo total de processamento
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);

        // Disparar evento de transação processada
        event('transaction.processed', [
            $transaction->id,
            'COMPLETED',
            $paymentResponse['gateway_id'],
            $processingTime
        ]);

        // Retornar resposta (sem carregar novamente as relações)
        return response()->json([
            'message' => 'Compra realizada com sucesso',
            'transaction' => new TransactionResource($transaction),
            'processing_time_ms' => $processingTime
        ], 201);
    }

    public function refund(Transaction $transaction)
    {
        $startTime = microtime(true);

        $this->authorize('process-refunds');

        if ($transaction->status === 'REFUNDED') {
            return response()->json([
                'message' => 'Esta transação já foi reembolsada'
            ], 422);
        }

        try {
            $refundResponse = $this->paymentService->refundPayment($transaction);

            // Atualizar o status na mesma instância, sem buscar novamente
            $transaction->status = 'REFUNDED';
            $transaction->save();

            // Garantir que todas as relações necessárias estão carregadas
            if (
                !$transaction->relationLoaded('client') ||
                !$transaction->relationLoaded('gateway') ||
                !$transaction->relationLoaded('products')
            ) {
                $transaction->load(['client', 'gateway', 'products']);
            }

            // Calcular o tempo total de processamento
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'message' => 'Reembolso realizado com sucesso',
                'transaction' => new TransactionResource($transaction),
                'response' => $refundResponse,
                'processing_time_ms' => $processingTime
            ]);
        } catch (\Exception $e) {
            // Log de falha no reembolso
            Log::channel('transactions')->error('Refund failed', [
                'transaction_id' => $transaction->id,
                'external_id' => $transaction->external_id,
                'gateway_id' => $transaction->gateway_id,
                'amount' => $transaction->amount,
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Falha ao processar o reembolso',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
