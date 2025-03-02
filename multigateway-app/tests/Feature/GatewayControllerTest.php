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
    $this->gateway1 = Gateway::where('name', 'Gateway 1')->first();
    $this->gateway2 = Gateway::where('name', 'Gateway 2')->first();
  }

  #[Test]
  public function it_lists_gateways_in_priority_order()
  {
    $response = $this->actingAs($this->adminUser)
      ->getJson('/api/gateways');

    $response->assertStatus(200)
      ->assertJsonCount(2)
      ->assertJson([
        [
          'name' => 'Gateway 1',
          'priority' => 1
        ],
        [
          'name' => 'Gateway 2',
          'priority' => 2
        ]
      ]);
  }

  #[Test]
  public function admin_can_toggle_gateway_status()
  {
    // Salvar o status original para restaurar depois
    $originalStatus = $this->gateway1->is_active;

    $response = $this->actingAs($this->adminUser)
      ->patchJson("/api/gateways/{$this->gateway1->id}/toggle");

    $response->assertStatus(200)
      ->assertJson([
        'is_active' => !$originalStatus
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

    $response->assertStatus(403); // Forbidden

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
        'priority' => $newPriority
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
}
