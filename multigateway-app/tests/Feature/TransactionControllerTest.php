<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $financeUser;
    protected $regularUser;
    protected $gateway;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar roles
        $adminRole = Role::create(['name' => 'ADMIN', 'description' => 'Administrator']);
        $financeRole = Role::create(['name' => 'FINANCE', 'description' => 'Finance']);
        $userRole = Role::create(['name' => 'USER', 'description' => 'Regular User']);

        // Criar usuários
        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $this->adminUser->roles()->attach($adminRole);

        $this->financeUser = User::factory()->create(['name' => 'Finance User']);
        $this->financeUser->roles()->attach($financeRole);

        $this->regularUser = User::factory()->create(['name' => 'Regular User']);
        $this->regularUser->roles()->attach($userRole);

        // Criar gateway
        $this->gateway = Gateway::create([
            'name' => 'Test Gateway',
            'is_active' => true,
            'priority' => 1
        ]);

        // Criar produto
        $this->product = Product::create([
            'name' => 'Test Product',
            'amount' => 1000 // R$ 10,00
        ]);
    }

    #[Test]
    public function user_can_process_purchase()
    {
        // Mock do PaymentService
        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('processPayment')
                          ->once()
                          ->andReturn([
                              'success' => true,
                              'gateway_id' => $this->gateway->id,
                              'external_id' => 'test-transaction-123',
                          ]);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        // Dados da compra
        $purchaseData = [
            'products' => [
                [
                    'id' => $this->product->id,
                    'quantity' => 2
                ]
            ],
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'card_number' => '5569000000006063',
            'card_cvv' => '010'
        ];

        // Fazer a compra
        $response = $this->actingAs($this->regularUser)
                         ->postJson('/api/purchase', $purchaseData);

        // Verificar resposta
        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Compra realizada com sucesso')
                 ->assertJsonStructure([
                     'message',
                     'transaction' => [
                         'id',
                         'client_id',
                         'gateway_id',
                         'external_id',
                         'status',
                         'amount',
                         'card_last_numbers',
                         'client',
                         'products'
                     ]
                 ]);

        // Verificar se a transação foi criada
        $this->assertDatabaseHas('transactions', [
            'external_id' => 'test-transaction-123',
            'status' => 'COMPLETED',
            'amount' => 2000, // 2 * R$ 10,00
            'card_last_numbers' => '6063'
        ]);

        // Verificar se o cliente foi criado
        $this->assertDatabaseHas('clients', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Verificar se os produtos foram associados à transação
        $transaction = Transaction::where('external_id', 'test-transaction-123')->first();
        $this->assertTrue($transaction->products->contains($this->product->id));
        $this->assertEquals(2, $transaction->products->find($this->product->id)->pivot->quantity);
    }

    #[Test]
    public function finance_user_can_refund_transaction()
    {
        // Criar cliente
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'test@example.com'
        ]);

        // Criar uma transação
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'test-transaction-456',
            'status' => 'COMPLETED',
            'amount' => 1500,
            'card_last_numbers' => '6063'
        ]);

        // Associar produto à transação
        $transaction->products()->attach($this->product->id, ['quantity' => 1]);

        // Mock do PaymentService para refund
        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('refundPayment')
                          ->once()
                          ->andReturn([
                              'refundId' => 'refund-123',
                              'status' => 'success'
                          ]);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        // Tentar reembolsar como usuário de finanças
        $response = $this->actingAs($this->financeUser)
                         ->postJson("/api/transactions/{$transaction->id}/refund");

        // Verificar resposta
        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Reembolso realizado com sucesso')
                 ->assertJsonPath('transaction.status', 'REFUNDED');

        // Verificar se a transação foi atualizada
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'REFUNDED'
        ]);
    }

    #[Test]
    public function regular_user_cannot_refund_transaction()
    {
        // Criar cliente
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'test@example.com'
        ]);

        // Criar uma transação
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'test-transaction-456',
            'status' => 'COMPLETED',
            'amount' => 1500,
            'card_last_numbers' => '6063'
        ]);

        // Associar produto à transação
        $transaction->products()->attach($this->product->id, ['quantity' => 1]);

        // Tentar reembolsar como usuário regular
        $response = $this->actingAs($this->regularUser)
                         ->postJson("/api/transactions/{$transaction->id}/refund");

        // Verificar resposta - deve ser proibido
        $response->assertStatus(403);

        // Verificar que a transação não foi alterada
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'COMPLETED'
        ]);
    }

    #[Test]
    public function it_prevents_refunding_already_refunded_transaction()
    {
        // Criar cliente
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'test@example.com'
        ]);

        // Criar uma transação já reembolsada
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'test-transaction-789',
            'status' => 'REFUNDED',
            'amount' => 1500,
            'card_last_numbers' => '6063'
        ]);

        // Tentar reembolsar como admin
        $response = $this->actingAs($this->adminUser)
                         ->postJson("/api/transactions/{$transaction->id}/refund");

        // Verificar resposta - deve retornar erro
        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Esta transação já foi reembolsada');
    }

    #[Test]
    public function it_lists_all_transactions()
    {
        // Criar alguns clientes
        $client1 = Client::create(['name' => 'Client 1', 'email' => 'client1@example.com']);
        $client2 = Client::create(['name' => 'Client 2', 'email' => 'client2@example.com']);

        // Criar algumas transações
        Transaction::create([
            'client_id' => $client1->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'trans-1',
            'status' => 'COMPLETED',
            'amount' => 1000,
            'card_last_numbers' => '1234'
        ]);

        Transaction::create([
            'client_id' => $client2->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'trans-2',
            'status' => 'COMPLETED',
            'amount' => 2000,
            'card_last_numbers' => '5678'
        ]);

        // Listar transações
        $response = $this->actingAs($this->adminUser)
                         ->getJson('/api/transactions');

        // Verificar resposta
        $response->assertStatus(200)
                 ->assertJsonCount(2)
                 ->assertJsonStructure([
                     '*' => [
                         'id',
                         'client_id',
                         'gateway_id',
                         'external_id',
                         'status',
                         'amount',
                         'card_last_numbers',
                         'client',
                         'gateway',
                         'products'
                     ]
                 ]);
    }

    #[Test]
    public function it_shows_transaction_details()
    {
        // Criar cliente
        $client = Client::create(['name' => 'Test Client', 'email' => 'test@example.com']);

        // Criar transação
        $transaction = Transaction::create([
            'client_id' => $client->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'show-transaction-test',
            'status' => 'COMPLETED',
            'amount' => 3000,
            'card_last_numbers' => '9876'
        ]);

        // Associar produtos
        $transaction->products()->attach($this->product->id, ['quantity' => 3]);

        // Obter detalhes da transação
        $response = $this->actingAs($this->regularUser)
                         ->getJson("/api/transactions/{$transaction->id}");

        // Verificar resposta
        $response->assertStatus(200)
                 ->assertJson([
                     'id' => $transaction->id,
                     'external_id' => 'show-transaction-test',
                     'status' => 'COMPLETED',
                     'amount' => 3000,
                     'card_last_numbers' => '9876'
                 ])
                 ->assertJsonPath('client.name', 'Test Client')
                 ->assertJsonPath('products.0.pivot.quantity', 3);
    }

    #[Test]
    public function it_handles_payment_failure()
    {
        // Mock do PaymentService para simular falha
        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('processPayment')
                          ->once()
                          ->andReturn([
                              'success' => false,
                              'errors' => ['Erro no processamento do pagamento', 'Cartão recusado']
                          ]);

        $this->app->instance(PaymentService::class, $mockPaymentService);

        // Dados da compra
        $purchaseData = [
            'products' => [
                [
                    'id' => $this->product->id,
                    'quantity' => 1
                ]
            ],
            'client_name' => 'Failed Payment',
            'client_email' => 'failed@example.com',
            'card_number' => '5569000000006063',
            'card_cvv' => '010'
        ];

        // Tentar fazer a compra
        $response = $this->actingAs($this->regularUser)
                         ->postJson('/api/purchase', $purchaseData);

        // Verificar resposta
        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Falha no processamento do pagamento')
                 ->assertJsonStructure([
                     'message',
                     'errors'
                 ]);

        // Verificar que nenhuma transação foi criada
        $this->assertDatabaseMissing('transactions', [
            'status' => 'COMPLETED',
            'amount' => 1000,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
