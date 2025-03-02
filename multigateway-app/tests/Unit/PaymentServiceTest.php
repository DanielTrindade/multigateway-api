<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Services\Payment\Gateway1;
use App\Services\Payment\Gateway2;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Verificar se existem gateways do seed, caso contrário, criar
        if (Gateway::count() == 0) {
            Gateway::create([
                'id' => 1,
                'name' => 'Gateway 1',
                'is_active' => true,
                'priority' => 1
            ]);

            Gateway::create([
                'id' => 2,
                'name' => 'Gateway 2',
                'is_active' => true,
                'priority' => 2
            ]);
        }
    }

    #[Test]
    public function it_tries_second_gateway_when_first_fails()
    {
        // Mock para o Gateway1
        $mockGateway1 = Mockery::mock(Gateway1::class);
        $mockGateway1->shouldReceive('pay')
                    ->once()
                    ->andThrow(new \Exception('Simulando falha no Gateway 1'));

        // Mock para o Gateway2 que terá sucesso
        $mockGateway2 = Mockery::mock(Gateway2::class);
        $mockGateway2->shouldReceive('pay')
                    ->once()
                    ->andReturn([
                        'transactionId' => 'mock-transaction-123',
                        'status' => 'approved'
                    ]);

        // Configura o serviço com gateways mockados diretamente
        $paymentService = new PaymentService([
            [
                'id' => 1,
                'instance' => $mockGateway1
            ],
            [
                'id' => 2,
                'instance' => $mockGateway2
            ]
        ]);

        // Dados do pagamento
        $paymentData = [
            'amount' => 1000,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'card_number' => '5569000000006063',
            'cvv' => '010'
        ];

        // Processar pagamento
        $result = $paymentService->processPayment($paymentData);

        // Verificar resultado
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['gateway_id']);
        $this->assertEquals('mock-transaction-123', $result['external_id']);
    }

    #[Test]
    public function it_fails_when_all_gateways_fail()
    {
        // Mock para o Gateway1
        $mockGateway1 = Mockery::mock(Gateway1::class);
        $mockGateway1->shouldReceive('pay')
                    ->once()
                    ->andThrow(new \Exception('Simulando falha no Gateway 1'));

        // Mock para o Gateway2
        $mockGateway2 = Mockery::mock(Gateway2::class);
        $mockGateway2->shouldReceive('pay')
                    ->once()
                    ->andThrow(new \Exception('Simulando falha no Gateway 2'));

        // Configura o serviço com gateways mockados diretamente
        $paymentService = new PaymentService([
            [
                'id' => 1,
                'instance' => $mockGateway1
            ],
            [
                'id' => 2,
                'instance' => $mockGateway2
            ]
        ]);

        // Dados do pagamento
        $paymentData = [
            'amount' => 1000,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'card_number' => '5569000000006063',
            'cvv' => '010'
        ];

        // Processar pagamento
        $result = $paymentService->processPayment($paymentData);

        // Verificar resultado
        $this->assertFalse($result['success']);
        $this->assertCount(2, $result['errors']);
    }

    #[Test]
    public function it_skips_inactive_gateways()
    {
        $gateway1 = Gateway::find(1);
        $originalStatus = $gateway1->is_active;

        // Desativar Gateway 1 temporariamente
        $gateway1->is_active = false;
        $gateway1->save();

        // Mock apenas para o Gateway2
        $mockGateway2 = Mockery::mock(Gateway2::class);
        $mockGateway2->shouldReceive('pay')
                    ->once()
                    ->andReturn([
                        'transactionId' => 'mock-transaction-456',
                        'status' => 'approved'
                    ]);

        // Configura o serviço com gateways mockados diretamente
        $paymentService = new PaymentService([
            [
                'id' => 2,
                'instance' => $mockGateway2
            ]
        ]);

        // Dados do pagamento
        $paymentData = [
            'amount' => 1000,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'card_number' => '5569000000006063',
            'cvv' => '010'
        ];

        // Processar pagamento
        $result = $paymentService->processPayment($paymentData);

        // Verificar resultado
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['gateway_id']);

        // Restaurar status original
        $gateway1->is_active = $originalStatus;
        $gateway1->save();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
