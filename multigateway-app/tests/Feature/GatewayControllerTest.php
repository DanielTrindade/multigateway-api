<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GatewayControllerTest extends TestCase
{
  use RefreshDatabase;

  protected $adminUser;
  protected $regularUser;

  protected function setUp(): void
  {
    parent::setUp();

    // Criar roles
    $adminRole = Role::create(['name' => 'ADMIN', 'description' => 'Administrator']);
    $userRole = Role::create(['name' => 'USER', 'description' => 'Regular User']);

    // Criar usuários
    $this->adminUser = User::factory()->create(['name' => 'Admin User']);
    $this->adminUser->roles()->attach($adminRole);

    $this->regularUser = User::factory()->create(['name' => 'Regular User']);
    $this->regularUser->roles()->attach($userRole);

    // Criar gateways de exemplo
    Gateway::create([
      'name' => 'Gateway 1',
      'is_active' => true,
      'priority' => 1,
      'credentials' => json_encode([
        'api_key' => 'test_key_1',
        'api_secret' => 'test_secret_1'
      ])
    ]);

    Gateway::create([
      'name' => 'Gateway 2',
      'is_active' => true,
      'priority' => 2,
      'credentials' => json_encode([
        'api_key' => 'test_key_2',
        'api_secret' => 'test_secret_2'
      ])
    ]);
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
    $gateway = Gateway::first();

    $response = $this->actingAs($this->adminUser)
      ->patchJson("/api/gateways/{$gateway->id}/toggle");

    $response->assertStatus(200)
      ->assertJson([
        'is_active' => !$gateway->is_active
      ]);

    $this->assertDatabaseHas('gateways', [
      'id' => $gateway->id,
      'is_active' => !$gateway->is_active
    ]);
  }

  #[Test]
  public function regular_user_cannot_toggle_gateway_status()
  {
    $gateway = Gateway::first();
    $originalStatus = $gateway->is_active;

    $response = $this->actingAs($this->regularUser)
      ->patchJson("/api/gateways/{$gateway->id}/toggle");

    $response->assertStatus(403); // Forbidden

    $this->assertDatabaseHas('gateways', [
      'id' => $gateway->id,
      'is_active' => $originalStatus
    ]);
  }

  #[Test]
  public function admin_can_update_gateway_priority()
  {
    $gateway = Gateway::first();
    $newPriority = 10;

    $response = $this->actingAs($this->adminUser)
      ->patchJson("/api/gateways/{$gateway->id}/priority", [
        'priority' => $newPriority
      ]);

    $response->assertStatus(200)
      ->assertJson([
        'priority' => $newPriority
      ]);

    $this->assertDatabaseHas('gateways', [
      'id' => $gateway->id,
      'priority' => $newPriority
    ]);
  }

  #[Test]
  public function regular_user_cannot_update_gateway_priority()
  {
    $gateway = Gateway::first();
    $originalPriority = $gateway->priority;
    $newPriority = 99;

    $response = $this->actingAs($this->regularUser)
      ->patchJson("/api/gateways/{$gateway->id}/priority", [
        'priority' => $newPriority
      ]);

    $response->assertStatus(403); // Forbidden

    $this->assertDatabaseHas('gateways', [
      'id' => $gateway->id,
      'priority' => $originalPriority
    ]);
  }
}
