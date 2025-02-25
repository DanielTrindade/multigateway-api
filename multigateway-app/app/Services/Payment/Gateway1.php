<?php
namespace App\Services\Payment;

use App\Services\Payment\PaymentGatewayInterface as PaymentPaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

class Gateway1 implements PaymentPaymentGatewayInterface
{
    private $apiUrl;
    private $email;
    private $token;
    private $bearerToken;

    public function __construct()
    {
        $this->apiUrl = config('services.gateway1.url');
        $this->email = config('services.gateway1.email');
        $this->token = config('services.gateway1.token');
        $this->authenticate();
    }

    private function authenticate()
    {
        $response = Http::post("{$this->apiUrl}/login", [
            'email' => $this->email,
            'token' => $this->token,
        ]);

        if ($response->successful()) {
            $this->bearerToken = $response->json()['token'];
        } else {
            throw new \Exception('Failed to authenticate with Gateway 1');
        }
    }

    public function pay(array $data): array
    {
        $response = Http::withToken($this->bearerToken)
            ->post("{$this->apiUrl}/transactions", [
                'amount' => $data['amount'],
                'name' => $data['name'],
                'email' => $data['email'],
                'cardNumber' => $data['card_number'],
                'cvv' => $data['cvv'],
            ]);

        return $response->json();
    }

    public function refund(string $transactionId): array
    {
        $response = Http::withToken($this->bearerToken)
            ->post("{$this->apiUrl}/transactions/{$transactionId}/charge_back");

        return $response->json();
    }

    public function getTransactions(): array
    {
        $response = Http::withToken($this->bearerToken)
            ->get("{$this->apiUrl}/transactions");

        return $response->json();
    }
}

