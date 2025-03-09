<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\Gateway;

class ObservabilityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Registrar canal de log estruturado para transações
        $this->registerTransactionLogging();

        // Registrar canal de log para eventos de gateway
        $this->registerGatewayLogging();

        // Registrar handlers para eventos de modelo
        $this->registerModelEventHandlers();
    }

    /**
     * Configurar o logging de transações
     */
    private function registerTransactionLogging(): void
    {
        Event::listen('transaction.processed', function ($transactionId, $status, $gatewayId, $processingTimeMs) {
            $transaction = Transaction::with(['client', 'gateway', 'products'])->find($transactionId);

            if (!$transaction) {
                return;
            }

            // Log estruturado da transação
            Log::channel('transactions')->info('Transaction processed', [
                'transaction_id' => $transaction->id,
                'external_id' => $transaction->external_id,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'amount_formatted' => 'R$ ' . number_format($transaction->amount / 100, 2, ',', '.'),
                'gateway' => [
                    'id' => $transaction->gateway_id,
                    'name' => $transaction->gateway ? $transaction->gateway->name : 'Unknown',
                    'type' => $transaction->gateway ? $transaction->gateway->type : 'Unknown',
                ],
                'client' => [
                    'id' => $transaction->client_id,
                    'name' => $transaction->client ? $transaction->client->name : 'Unknown',
                    'email' => $transaction->client ? $transaction->client->email : 'Unknown',
                ],
                'products' => $transaction->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $product->pivot->quantity,
                        'amount' => $product->amount,
                        'subtotal' => $product->amount * $product->pivot->quantity,
                    ];
                }),
                'metadata' => [
                    'processing_time_ms' => $processingTimeMs,
                    'timestamp' => now()->toIso8601String(),
                    'environment' => app()->environment(),
                ],
            ]);
        });

        // Evento para reembolsos
        Event::listen('transaction.refunded', function ($transactionId, $processingTimeMs) {
            $transaction = Transaction::with(['client', 'gateway'])->find($transactionId);

            if (!$transaction) {
                return;
            }

            // Log estruturado do reembolso
            Log::channel('transactions')->info('Transaction refunded', [
                'transaction_id' => $transaction->id,
                'external_id' => $transaction->external_id,
                'original_status' => 'COMPLETED',
                'new_status' => 'REFUNDED',
                'amount' => $transaction->amount,
                'amount_formatted' => 'R$ ' . number_format($transaction->amount / 100, 2, ',', '.'),
                'gateway' => [
                    'id' => $transaction->gateway_id,
                    'name' => $transaction->gateway ? $transaction->gateway->name : 'Unknown',
                ],
                'client' => [
                    'id' => $transaction->client_id,
                    'name' => $transaction->client ? $transaction->client->name : 'Unknown',
                    'email' => $transaction->client ? $transaction->client->email : 'Unknown',
                ],
                'metadata' => [
                    'processing_time_ms' => $processingTimeMs,
                    'timestamp' => now()->toIso8601String(),
                    'environment' => app()->environment(),
                ],
            ]);
        });
    }

    /**
     * Configurar o logging de eventos de gateway
     */
    private function registerGatewayLogging(): void
    {
        Event::listen('gateway.request', function ($gatewayId, $operation, $data) {
            $gateway = Gateway::find($gatewayId);

            // Log estruturado da requisição para o gateway
            Log::channel('gateways')->info('Gateway request', [
                'gateway' => [
                    'id' => $gatewayId,
                    'name' => $gateway ? $gateway->name : 'Unknown',
                    'type' => $gateway ? $gateway->type : 'Unknown',
                ],
                'operation' => $operation,
                'request_data' => array_merge($data, [
                    // Remover dados sensíveis
                    'card_number' => isset($data['card_number']) ? '****' . substr($data['card_number'], -4) : null,
                    'cvv' => isset($data['cvv']) ? '***' : null,
                ]),
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        Event::listen('gateway.response', function ($gatewayId, $operation, $status, $response, $processingTimeMs) {
            $gateway = Gateway::find($gatewayId);

            // Log estruturado da resposta do gateway
            Log::channel('gateways')->info('Gateway response', [
                'gateway' => [
                    'id' => $gatewayId,
                    'name' => $gateway ? $gateway->name : 'Unknown',
                    'type' => $gateway ? $gateway->type : 'Unknown',
                ],
                'operation' => $operation,
                'status' => $status,
                'response' => $response,
                'metadata' => [
                    'processing_time_ms' => $processingTimeMs,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        });
    }

    /**
     * Registrar listeners para eventos de modelo
     */
    private function registerModelEventHandlers(): void
    {
        // Monitorar quando gateways são ativados/desativados
        Gateway::updated(function ($gateway) {
            if ($gateway->isDirty('is_active')) {
                $action = $gateway->is_active ? 'activated' : 'deactivated';

                Log::channel('system')->info("Gateway {$action}", [
                    'gateway_id' => $gateway->id,
                    'gateway_name' => $gateway->name,
                    'gateway_type' => $gateway->type,
                    'user_id' => auth()->id(),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            // Monitorar mudanças de prioridade
            if ($gateway->isDirty('priority')) {
                Log::channel('system')->info("Gateway priority changed", [
                    'gateway_id' => $gateway->id,
                    'gateway_name' => $gateway->name,
                    'old_priority' => $gateway->getOriginal('priority'),
                    'new_priority' => $gateway->priority,
                    'user_id' => auth()->id(),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        });
    }
}
