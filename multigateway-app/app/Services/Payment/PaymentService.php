<?php

namespace App\Services\Payment;

use App\Models\Gateway;
use App\Models\Transaction;

class PaymentService
{
    protected $gateways = [];

    public function __construct()
    {
        // Carregar todos os gateways ativos em ordem de prioridade
        $dbGateways = Gateway::where('is_active', true)
                             ->orderBy('priority')
                             ->get();

        foreach ($dbGateways as $gateway) {
            $gatewayClass = "App\\Services\\Payment\\Gateway{$gateway->id}";
            if (class_exists($gatewayClass)) {
                $this->gateways[] = [
                    'id' => $gateway->id,
                    'instance' => new $gatewayClass(),
                ];
            }
        }
    }

    public function processPayment(array $paymentData)
    {
        $errors = [];

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

    public function refundPayment(Transaction $transaction)
    {
        $gatewayClass = "App\\Services\\Payment\\Gateway{$transaction->gateway_id}";
        if (!class_exists($gatewayClass)) {
            throw new \Exception("Gateway não encontrado para reembolso");
        }

        $gateway = new $gatewayClass();
        return $gateway->refund($transaction->external_id);
    }
}
