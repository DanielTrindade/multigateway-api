<?php

namespace App\Services\Payment;

use App\Services\Payment\PaymentGatewayInterface as PaymentPaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Gateway2 implements PaymentPaymentGatewayInterface
{
    private $apiUrl;
    private $authToken;
    private $authSecret;

    public function __construct()
    {
        $this->apiUrl = config('services.gateway2.url');
        $this->authToken = config('services.gateway2.auth_token');
        $this->authSecret = config('services.gateway2.auth_secret');
    }

    public function pay(array $data): array
    {
        $response = Http::withHeaders([
            'Gateway-Auth-Token' => $this->authToken,
            'Gateway-Auth-Secret' => $this->authSecret,
        ])->post("{$this->apiUrl}/transacoes", [
            'valor' => $data['amount'],
            'nome' => $data['name'],
            'email' => $data['email'],
            'numeroCartao' => $data['card_number'],
            'cvv' => $data['cvv'],
        ]);

        return $response->json();
    }

    public function refund(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Gateway-Auth-Token' => $this->authToken,
                'Gateway-Auth-Secret' => $this->authSecret,
            ])->post("{$this->apiUrl}/transacoes/reembolso", [
                'id' => $transactionId
            ]);

            if (!$response->successful()) {
                Log::error("Erro no reembolso Gateway 2: " . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Erro no reembolso Gateway 2: " . $e->getMessage());
            throw $e;
        }
    }

    public function getTransactions(): array
    {
        $response = Http::withHeaders([
            'Gateway-Auth-Token' => $this->authToken,
            'Gateway-Auth-Secret' => $this->authSecret,
        ])->get("{$this->apiUrl}/transacoes");

        return $response->json();
    }
}
