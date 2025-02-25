<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    public function pay(array $data): array;
    public function refund(string $transactionId): array;
    public function getTransactions(): array;
}
