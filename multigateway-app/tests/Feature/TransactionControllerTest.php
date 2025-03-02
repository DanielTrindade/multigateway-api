<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $adminUser;
    protected $financeUser;
    protected $regularUser;
    protected $gateway;
    protected $product;
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Usar usuários existentes do seed
        $this->adminUser = User::where('email', 'admin@example.com')->first();
        $this->financeUser = User::where('email', 'finance@example.com')->first();
        $this->regularUser = User::where('email', 'user@example.com')->first();

        // Usar gateway existente do seed
        $this->gateway = Gateway::where('name', 'Gateway 1')->first();

        // Usar produto existente do seed
        $this->product = Product::first();

        // Usar cliente existente do seed
        $this->client = Client::first();
    }

    #[Test]
    public function user_can_process_purchase()
    {
        // Mock do PaymentService
        $mock = $this->partialMock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('processPayment')
                ->once()
                ->andReturn([
                    'success' => true,
                    'gateway_id' => $this->gateway->id,
                    'external_id' => 'test-transaction-123',
                ]);
        });

        // Dados da compra
        $purchaseData = [
            'products' => [
                [
                    'id' => $this->product->id,
                    'quantity' => 2
                ]
            ],
            'client_name' => 'New Test Client',
            'client_email' => 'test@gmail.com',
            'card_number' => '5569000000006063',
            'card_cvv' => '010'
        ];

        // Fazer a compra
        $response = $this->postJson('/api/purchase', $purchaseData);

        // Verificar resposta
        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Compra realizada com sucesso');

        // Verificar se a transação foi criada
        $this->assertDatabaseHas('transactions', [
            'external_id' => 'test-transaction-123',
            'status' => 'COMPLETED',
            'card_last_numbers' => '6063'
        ]);
    }

    #[Test]
    public function finance_user_can_refund_transaction()
    {
        $this->withoutExceptionHandling();

        // Criar uma transação para reembolso
        $transaction = Transaction::create([
            'client_id' => $this->client->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'test-transaction-456',
            'status' => 'COMPLETED',
            'amount' => 1500,
            'card_last_numbers' => '6063'
        ]);

        // Associar produto à transação
        $transaction->products()->attach($this->product->id, ['quantity' => 1]);

        // Importante: vamos usar partialMock para garantir que o mock seja aplicado corretamente
        $this->partialMock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('refundPayment')
                 ->once()
                 ->andReturn([
                     'refundId' => 'refund-123',
                     'status' => 'success'
                 ]);
        });

        // Desativar os triggers SQL que poderiam causar erros
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Tentar reembolsar como usuário de finanças
        $response = $this->actingAs($this->financeUser)
                         ->postJson("/api/transactions/{$transaction->id}/refund");

        // Restaurar configuração SQL
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Verificar resposta
        if ($response->status() !== 200) {
            dump("Resposta de erro: ", $response->json());
        }

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Reembolso realizado com sucesso');
    }

    #[Test]
    public function it_prevents_refunding_already_refunded_transaction()
    {
        // Criar uma transação já reembolsada
        $transaction = Transaction::create([
            'client_id' => $this->client->id,
            'gateway_id' => $this->gateway->id,
            'external_id' => 'test-transaction-already-refunded',
            'status' => 'REFUNDED',
            'amount' => 1500,
            'card_last_numbers' => '6063'
        ]);

        // Verificar explicitamente que foi criada com status REFUNDED
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'REFUNDED'
        ]);

        // Associar produto à transação
        $transaction->products()->attach($this->product->id, ['quantity' => 1]);

        // Verificar que o controller verifica o status REFUNDED antes de tentar reembolsar
        $response = $this->actingAs($this->adminUser)
                         ->postJson("/api/transactions/{$transaction->id}/refund");

        // Verificar resposta
        $response->assertStatus(422)
                 ->assertJson([
                     'message' => 'Esta transação já foi reembolsada'
                 ]);
    }

    #[Test]
    public function it_lists_all_transactions()
    {
        // Listar transações
        $response = $this->actingAs($this->adminUser)
                         ->getJson('/api/transactions');

        // Verificar resposta - deve ser sucesso
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     '*' => [
                         'id',
                         'client_id',
                         'gateway_id',
                         'external_id',
                         'status',
                         'amount',
                         'card_last_numbers'
                     ]
                 ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
