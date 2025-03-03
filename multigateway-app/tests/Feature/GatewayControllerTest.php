<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GatewayControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $adminUser;
    protected $regularUser;
    protected $gateway1;
    protected $gateway2;

    protected function setUp(): void
    {
        parent::setUp();

        // Usar usuários do seed
        $this->adminUser = User::where('email', 'admin@example.com')->first();
        $this->regularUser = User::where('email', 'user@example.com')->first();

        // Usar gateways do seed
        $this->gateway1 = Gateway::firstOrCreate(
            ['name' => 'Gateway 1'],
            [
                'type' => 'gateway1',
                'is_active' => true,
                'priority' => 1
            ]
        );

        $this->gateway2 = Gateway::firstOrCreate(
            ['name' => 'Gateway 2'],
            [
                'type' => 'gateway2',
                'is_active' => true,
                'priority' => 2
            ]
        );
    }

    #[Test]
    public function admin_can_create_gateway_with_type()
    {
        $gatewayData = [
            'name' => 'New Test Gateway',
            'type' => 'gateway3',
            'is_active' => true,
            'priority' => 3,
            'credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret'
            ]
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/gateways', $gatewayData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'New Test Gateway',
                    'type' => 'gateway3',
                    'is_active' => true,
                    'priority' => 3
                ]
            ]);

        $this->assertDatabaseHas('gateways', [
            'name' => 'New Test Gateway',
            'type' => 'gateway3'
        ]);
    }

    #[Test]
    public function admin_can_update_gateway_type()
    {
        $updateData = [
            'type' => 'updated_gateway_type'
        ];

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/gateways/{$this->gateway1->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'updated_gateway_type'
                ]
            ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway1->id,
            'type' => 'updated_gateway_type'
        ]);
    }

    #[Test]
    public function it_lists_gateways_in_priority_order()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/gateways');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta'
            ]);

        // Verificar que os gateways estão na ordem correta dentro de 'data'
        $this->assertEquals('Gateway 1', $response->json('data.0.name'));
        $this->assertEquals('Gateway 2', $response->json('data.1.name'));
        $this->assertEquals(1, $response->json('data.0.priority'));
        $this->assertEquals(2, $response->json('data.1.priority'));
    }

    public function admin_can_toggle_gateway_status()
    {
        // Salvar o status original para restaurar depois
        $originalStatus = $this->gateway1->is_active;

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/gateways/{$this->gateway1->id}/toggle");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_active' => !$originalStatus
                ]
            ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway1->id,
            'is_active' => !$originalStatus
        ]);

        // Restaurar ao status original após o teste
        $this->gateway1->is_active = $originalStatus;
        $this->gateway1->save();
    }

    #[Test]
    public function regular_user_cannot_toggle_gateway_status()
    {
        $originalStatus = $this->gateway1->is_active;

        $response = $this->actingAs($this->regularUser)
            ->patchJson("/api/gateways/{$this->gateway1->id}/toggle");

        $response->assertStatus(403);

        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway1->id,
            'is_active' => $originalStatus
        ]);
    }

    #[Test]
    public function admin_can_update_gateway_priority()
    {
        $originalPriority = $this->gateway1->priority;
        $newPriority = 10;

        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/gateways/{$this->gateway1->id}/priority", [
                'priority' => $newPriority
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Prioridade atualizada com sucesso',
                'gateway' => [
                    'priority' => $newPriority
                ]
            ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway1->id,
            'priority' => $newPriority
        ]);

        // Restaurar a prioridade original após o teste
        $this->gateway1->priority = $originalPriority;
        $this->gateway1->save();
    }

    #[Test]
    public function regular_user_cannot_update_gateway_priority()
    {
        $originalPriority = $this->gateway1->priority;
        $newPriority = 99;

        $response = $this->actingAs($this->regularUser)
            ->patchJson("/api/gateways/{$this->gateway1->id}/priority", [
                'priority' => $newPriority
            ]);

        $response->assertStatus(403); // Forbidden

        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway1->id,
            'priority' => $originalPriority
        ]);
    }

    #[Test]
    public function admin_can_reorder_multiple_gateway_priorities()
    {
        // Criar um terceiro gateway para teste
        $gateway3 = Gateway::factory()->create([
            'name' => 'Gateway 3',
            'type' => 'gateway3',
            'priority' => 3,
            'is_active' => true
        ]);

        // Nova ordem desejada
        $newOrder = [
            ['id' => $this->gateway1->id, 'priority' => 3],
            ['id' => $this->gateway2->id, 'priority' => 1],
            ['id' => $gateway3->id, 'priority' => 2]
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/gateways/reorder', [
                'gateways' => $newOrder
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Prioridades reordenadas com sucesso'
            ]);

        // Verificar se as prioridades foram atualizadas corretamente
        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway1->id,
            'priority' => 3
        ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway2->id,
            'priority' => 1
        ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway3->id,
            'priority' => 2
        ]);
    }

    #[Test]
    public function admin_can_normalize_gateway_priorities()
    {
        // Criar gateways com prioridades não consecutivas
        Gateway::where('id', $this->gateway1->id)->update(['priority' => 5]);
        Gateway::where('id', $this->gateway2->id)->update(['priority' => 10]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/gateways/normalize');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Prioridades normalizadas com sucesso'
            ]);

        // Recarregar os modelos para obter valores atualizados
        $this->gateway1->refresh();
        $this->gateway2->refresh();

        // Verificar se as prioridades foram normalizadas para 1 e 2
        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway1->id,
            'priority' => 1
        ]);

        $this->assertDatabaseHas('gateways', [
            'id' => $this->gateway2->id,
            'priority' => 2
        ]);
    }

    #[Test]
    public function regular_user_cannot_reorder_or_normalize_priorities()
    {
        // Tentar reordenar como usuário normal
        $response1 = $this->actingAs($this->regularUser)
            ->postJson('/api/gateways/reorder', [
                'gateways' => [
                    ['id' => $this->gateway1->id, 'priority' => 2],
                    ['id' => $this->gateway2->id, 'priority' => 1]
                ]
            ]);

        $response1->assertStatus(403); // Forbidden

        // Tentar normalizar como usuário normal
        $response2 = $this->actingAs($this->regularUser)
            ->postJson('/api/gateways/normalize');

        $response2->assertStatus(403); // Forbidden
    }
}
