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
        // Carregar todos os gateways ativos em ordem de prioridade
        $dbGateways = Gateway::where('is_active', true)
                            ->orderBy('priority')
                            ->get();

        foreach ($dbGateways as $gateway) {
            $gatewayClass = $this->getGatewayClass($gateway);

            if (class_exists($gatewayClass)) {
                try {
                    $this->gateways[] = [
                        'id' => $gateway->id,
                        'instance' => App::make($gatewayClass),
                    ];
                } catch (\Exception $e) {
                    Log::error("Falha ao instanciar gateway: {$e->getMessage()}");
                }
            } else {
                Log::warning("Classe do gateway não encontrada: {$gatewayClass}");
            }
        }
    }

    /**
     * Determina o nome da classe do gateway
     */
    protected function getGatewayClass(Gateway $gateway)
    {
        return "App\\Services\\Payment\\Gateway{$gateway->id}";
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
        $gatewayClass = "App\\Services\\Payment\\Gateway{$gatewayId}";

        if (!class_exists($gatewayClass)) {
            throw new \Exception("Gateway não encontrado para reembolso");
        }

        $gateway = App::make($gatewayClass);
        return $gateway->refund($transaction->external_id);
    }
}
