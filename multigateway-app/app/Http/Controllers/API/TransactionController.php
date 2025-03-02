<?php

namespace App\Http\Controllers\API;

use App\Models\Client;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;

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

        // Retornar resposta
        $transaction->load(['client', 'products']);
        return response()->json([
            'message' => 'Compra realizada com sucesso',
            'transaction' => new TransactionResource($transaction)
        ], 201);
    }

    public function refund(Transaction $transaction)
    {
        $this->authorize('process-refunds');

        if ($transaction->status === 'REFUNDED') {
            return response()->json([
                'message' => 'Esta transação já foi reembolsada'
            ], 422);
        }

        try {
            $refundResponse = $this->paymentService->refundPayment($transaction);
            $transaction = Transaction::findOrFail($transaction->id);
            $transaction->status = 'REFUNDED';
            $transaction->save();

            return response()->json([
                'message' => 'Reembolso realizado com sucesso',
                'transaction' => new TransactionResource($transaction),
                'response' => $refundResponse
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Falha ao processar o reembolso',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
