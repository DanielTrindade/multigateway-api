<?php

namespace App\Http\Controllers\API;

use App\Models\Client;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
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
        $transactions = Transaction::with(['client', 'gateway', 'products'])->paginate(20);
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

        // Calcular o total
        $total = 0;
        $products = [];

        foreach ($validatedData['products'] as $item) {
            $product = Product::findOrFail($item['id']);
            $total += $product->amount * $item['quantity'];
            $products[] = [
                'id' => $product->id,
                'quantity' => $item['quantity'],
            ];
        }

        // Verificar ou criar cliente
        $client = Client::firstOrCreate(
            ['email' => $validatedData['client_email']],
            ['name' => $validatedData['client_name']]
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

        // Adicionar produtos à transação
        foreach ($products as $product) {
            $transaction->products()->attach($product['id'], [
                'quantity' => $product['quantity']
            ]);
        }

        // Calcular o tempo total de processamento
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);

        // Disparar evento de transação processada
        event('transaction.processed', [
            $transaction->id,
            'COMPLETED',
            $paymentResponse['gateway_id'],
            $processingTime
        ]);

        // Retornar resposta
        $transaction->load(['client', 'products']);
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

            // Atualizar status da transação
            $transaction = Transaction::findOrFail($transaction->id);
            $transaction->status = 'REFUNDED';
            $transaction->save();

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
