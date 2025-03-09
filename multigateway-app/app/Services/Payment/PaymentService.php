<?php

namespace App\Services\Payment;

use App\Models\Gateway;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class PaymentService
{
    protected $gateways = [];
    protected $cachedGateways = null;

    /**
     * Construtor que facilita a injeção de mocks para testes
     *
     * @param array $mockedGateways Gateways mockados para testes
     */
    public function __construct(array $mockedGateways = [])
    {
        if (!empty($mockedGateways)) {
            $this->gateways = $mockedGateways;
        } else {
            $this->loadGateways();
        }
    }

    /**
     * Carrega os gateways ativos em ordem de prioridade
     */
    protected function loadGateways()
    {
        try {
            // Usar cache apenas para a lista de IDs e prioridades
            $gatewaysList = Cache::store('redis')->remember('active_gateways_list', 30*60, function() {
                return Gateway::where('is_active', true)
                    ->orderBy('priority')
                    ->pluck('id');
            });

            // Carregar todos os gateways de uma vez com eager loading
            if (count($gatewaysList) > 0) {
                $this->cachedGateways = Gateway::whereIn('id', $gatewaysList)
                    ->orderBy('priority')
                    ->get();

                foreach ($this->cachedGateways as $gateway) {
                    try {
                        $instance = $this->getGatewayInstance($gateway);
                        if ($instance) {
                            $this->gateways[] = [
                                'id' => $gateway->id,
                                'instance' => $instance
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error("Erro ao inicializar gateway {$gateway->name}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Erro ao carregar gateways: " . $e->getMessage());
        }
    }

    /**
     * Cria uma instância do gateway com base no tipo
     */
    protected function getGatewayInstance($gateway)
    {
        // Mapeamento dos tipos de gateway para suas classes
        $gatewayTypes = [
            'gateway1' => Gateway1::class,
            'gateway2' => Gateway2::class,
            // Adicionar novos gateways aqui
        ];

        $type = strtolower($gateway->type);

        if (isset($gatewayTypes[$type])) {
            $class = $gatewayTypes[$type];
            return new $class();
        }

        Log::warning("Tipo de gateway não reconhecido: {$type}");
        return null;
    }

    protected function createGateway1($gateway)
    {
        $credentials = $gateway->credentials;
        return new Gateway1(
            config('services.gateway1.url'),
            $credentials['email'] ?? config('services.gateway1.email'),
            $credentials['token'] ?? config('services.gateway1.token')
        );
    }

    protected function createGateway2($gateway)
    {
        $credentials = $gateway->credentials;
        return new Gateway2(
            config('services.gateway2.url'),
            $credentials['auth_token'] ?? config('services.gateway2.auth_token'),
            $credentials['auth_secret'] ?? config('services.gateway2.auth_secret')
        );
    }

    /**
     * Processa um pagamento usando os gateways disponíveis
     */
    public function processPayment(array $paymentData)
    {
        $startTime = microtime(true);
        $errors = [];

        // Se não houver gateways disponíveis
        if (empty($this->gateways)) {
            return [
                'success' => false,
                'errors' => ['Nenhum gateway de pagamento disponível']
            ];
        }

        // Tentar processar o pagamento em cada gateway, em ordem de prioridade
        foreach ($this->gateways as $gateway) {
            try {
                $gatewayStartTime = microtime(true);

                // Disparar evento de requisição para o gateway
                event('gateway.request', [
                    $gateway['id'],
                    'payment',
                    $paymentData
                ]);

                $response = $gateway['instance']->pay($paymentData);
                $gatewayProcessingTime = round((microtime(true) - $gatewayStartTime) * 1000, 2);

                // Verificar se o pagamento foi bem-sucedido
                if (isset($response['id']) || isset($response['transactionId'])) {
                    // Sucesso - Retornar com gateway já carregado pra evitar nova consulta
                    $totalProcessingTime = round((microtime(true) - $startTime) * 1000, 2);

                    return [
                        'success' => true,
                        'gateway_id' => $gateway['id'],
                        'external_id' => $response['id'] ?? $response['transactionId'],
                        'response' => $response,
                        'processing_time_ms' => $totalProcessingTime
                    ];
                }

                $errors[] = "Gateway {$gateway['id']}: " . json_encode($response);
            } catch (\Exception $e) {
                $errors[] = "Gateway {$gateway['id']}: " . $e->getMessage();
            }
        }

        // Se chegou aqui, todos os gateways falharam
        return [
            'success' => false,
            'errors' => $errors,
            'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];
    }

    /**
     * Realiza o reembolso de uma transação
     */
    public function refundPayment(Transaction $transaction)
    {
        // Garantir que a transação já tem o gateway carregado
        if (!$transaction->relationLoaded('gateway')) {
            $transaction->load('gateway');
        }

        $startTime = microtime(true);
        $gatewayId = $transaction->gateway_id;

        // Encontrar o gateway que processou a transação
        $gatewayInstance = null;
        foreach ($this->gateways as $gateway) {
            if ($gateway['id'] == $gatewayId) {
                $gatewayInstance = $gateway['instance'];
                break;
            }
        }

        if (!$gatewayInstance) {
            // Se não encontrou o gateway nos já carregados, tenta buscar diretamente
            try {
                $dbGateway = Gateway::find($gatewayId);
                if (!$dbGateway) {
                    throw new \Exception("Gateway ID {$gatewayId} não encontrado no banco de dados");
                }

                $gatewayInstance = $this->getGatewayInstance($dbGateway);

                if (!$gatewayInstance) {
                    throw new \Exception("Não foi possível criar uma instância do gateway {$dbGateway->name}");
                }
            } catch (\Exception $e) {
                Log::error("Erro ao carregar gateway para reembolso: " . $e->getMessage());
                throw new \Exception("Gateway não encontrado para reembolso: " . $e->getMessage());
            }
        }

        try {
            $gatewayStartTime = microtime(true);

            // Disparar evento de requisição para o gateway
            event('gateway.request', [
                $gatewayId,
                'refund',
                ['transaction_id' => $transaction->external_id]
            ]);

            $result = $gatewayInstance->refund($transaction->external_id);
            $gatewayProcessingTime = round((microtime(true) - $gatewayStartTime) * 1000, 2);

            // Disparar evento de resposta do gateway
            event('gateway.response', [
                $gatewayId,
                'refund',
                'success',
                $result,
                $gatewayProcessingTime
            ]);

            // Disparar evento de reembolso concluído
            $totalProcessingTime = round((microtime(true) - $startTime) * 1000, 2);
            event('transaction.refunded', [$transaction->id, $totalProcessingTime]);

            return $result;
        } catch (\Exception $e) {
            $gatewayProcessingTime = round((microtime(true) - $gatewayStartTime) * 1000, 2);

            // Registrar erro no gateway
            event('gateway.response', [
                $gatewayId,
                'refund',
                'error',
                ['message' => $e->getMessage()],
                $gatewayProcessingTime
            ]);

            Log::error("Erro ao processar reembolso da transação {$transaction->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
