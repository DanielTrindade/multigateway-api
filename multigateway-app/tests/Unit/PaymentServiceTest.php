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
                'type' => 'gateway1',
                'is_active' => true,
                'priority' => 1
            ]);

            Gateway::create([
                'id' => 2,
                'name' => 'Gateway 2',
                'type' => 'gateway2',
                'is_active' => true,
                'priority' => 2
            ]);
        }
    }

    #[Test]
    public function it_loads_gateways_based_on_their_type()
    {
        // Criar gateways com tipos específicos
        $gateway1 = Gateway::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Test Gateway 1',
                'type' => 'gateway1',
                'is_active' => true,
                'priority' => 1
            ]
        );

        $gateway2 = Gateway::firstOrCreate(
            ['id' => 2],
            [
                'name' => 'Test Gateway 2',
                'type' => 'gateway2',
                'is_active' => true,
                'priority' => 2
            ]
        );

        $unknownGateway = Gateway::create([
            'name' => 'Unknown Gateway',
            'type' => 'unknown_type',
            'is_active' => true,
            'priority' => 3
        ]);

        // Mock para testar se o PaymentService usa os tipos corretos
        $mockedGateway1 = Mockery::mock(Gateway1::class);
        $mockedGateway1->shouldReceive('pay')->andReturn(['id' => 'test1']);

        $mockedGateway2 = Mockery::mock(Gateway2::class);
        $mockedGateway2->shouldReceive('pay')->andReturn(['transactionId' => 'test2']);

        // Mockear o método getGatewayInstance no PaymentService
        $paymentServiceMock = Mockery::mock(PaymentService::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        $paymentServiceMock->shouldReceive('getGatewayInstance')
            ->with(Mockery::on(function ($gateway) use ($gateway1) {
                return $gateway->id === $gateway1->id;
            }))
            ->andReturn($mockedGateway1);

        $paymentServiceMock->shouldReceive('getGatewayInstance')
            ->with(Mockery::on(function ($gateway) use ($gateway2) {
                return $gateway->id === $gateway2->id;
            }))
            ->andReturn($mockedGateway2);

        $paymentServiceMock->shouldReceive('getGatewayInstance')
            ->with(Mockery::on(function ($gateway) use ($unknownGateway) {
                return $gateway->id === $unknownGateway->id;
            }))
            ->andReturn(null);

        // Invocar o método loadGateways manualmente
        $this->invokeMethod($paymentServiceMock, 'loadGateways');

        // Verificar se apenas os gateways conhecidos foram carregados
        $gateways = $this->getPrivateProperty($paymentServiceMock, 'gateways');
        $this->assertCount(2, $gateways);
        $this->assertEquals($gateway1->id, $gateways[0]['id']);
        $this->assertEquals($gateway2->id, $gateways[1]['id']);
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

    /**
     * Helper para invocar métodos privados em testes
     */
    private function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper para acessar propriedades privadas em testes
     */
    private function getPrivateProperty($object, $propertyName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
