<?php

namespace App\Services\Payment;

use App\Models\Gateway;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class PaymentService
{
    protected $gateways = [];

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
            // Carregar todos os gateways ativos em ordem de prioridade
            $dbGateways = Gateway::where('is_active', true)
                ->orderBy('priority')
                ->get();

            Log::info("Carregando gateways. Encontrados: " . $dbGateways->count());

            foreach ($dbGateways as $gateway) {
                try {
                    // Determinar qual classe de gateway usar baseado no nome
                    if (strpos(strtolower($gateway->name), 'gateway 1') !== false) {
                        $gatewayInstance = $this->createGateway1($gateway);
                    } else if (strpos(strtolower($gateway->name), 'gateway 2') !== false) {
                        $gatewayInstance = $this->createGateway2($gateway);
                    } else {
                        Log::warning("Gateway não reconhecido: " . $gateway->name);
                        continue;
                    }

                    $this->gateways[] = [
                        'id' => $gateway->id,
                        'instance' => $gatewayInstance,
                    ];

                    Log::info("Gateway carregado com sucesso: " . $gateway->name);
                } catch (\Exception $e) {
                    Log::error("Erro ao carregar gateway {$gateway->name}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("Erro ao carregar gateways: " . $e->getMessage());
        }
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
                $response = $gateway['instance']->pay($paymentData);

                // Verificar se o pagamento foi bem-sucedido
                if (isset($response['id']) || isset($response['transactionId'])) {
                    return [
                        'success' => true,
                        'gateway_id' => $gateway['id'],
                        'external_id' => $response['id'] ?? $response['transactionId'],
                        'response' => $response
                    ];
                }

                $errors[] = "Gateway {$gateway['id']}: " . json_encode($response);
            } catch (\Exception $e) {
                $errors[] = "Gateway {$gateway['id']}: " . $e->getMessage();
            }
        }

        // Se chegou até aqui, todos os gateways falharam
        return [
            'success' => false,
            'errors' => $errors
        ];
    }

    /**
     * Realiza o reembolso de uma transação
     */
    public function refundPayment(Transaction $transaction)
    {
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

                if (strpos(strtolower($dbGateway->name), 'gateway 1') !== false) {
                    $gatewayInstance = $this->createGateway1($dbGateway);
                } else if (strpos(strtolower($dbGateway->name), 'gateway 2') !== false) {
                    $gatewayInstance = $this->createGateway2($dbGateway);
                } else {
                    throw new \Exception("Tipo de gateway não reconhecido: {$dbGateway->name}");
                }
            } catch (\Exception $e) {
                Log::error("Erro ao carregar gateway para reembolso: " . $e->getMessage());
                throw new \Exception("Gateway não encontrado para reembolso: " . $e->getMessage());
            }
        }

        try {
            Log::info("Iniciando reembolso da transação {$transaction->id} via gateway {$gatewayId}");
            $result = $gatewayInstance->refund($transaction->external_id);
            Log::info("Reembolso da transação {$transaction->id} processado com sucesso");
            return $result;
        } catch (\Exception $e) {
            Log::error("Erro ao processar reembolso da transação {$transaction->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
