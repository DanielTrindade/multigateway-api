<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
  use RefreshDatabase;

  protected $adminUser;
  protected $managerUser;
  protected $financeUser;
  protected $regularUser;

  protected function setUp(): void
  {
    parent::setUp();

    // Criar roles
    $adminRole = Role::create(['name' => 'ADMIN', 'description' => 'Administrator']);
    $managerRole = Role::create(['name' => 'MANAGER', 'description' => 'Manager']);
    $financeRole = Role::create(['name' => 'FINANCE', 'description' => 'Finance']);
    $userRole = Role::create(['name' => 'USER', 'description' => 'Regular User']);

    // Criar usuários com diferentes roles
    $this->adminUser = User::factory()->create(['name' => 'Admin User']);
    $this->adminUser->roles()->attach($adminRole);

    $this->managerUser = User::factory()->create(['name' => 'Manager User']);
    $this->managerUser->roles()->attach($managerRole);

    $this->financeUser = User::factory()->create(['name' => 'Finance User']);
    $this->financeUser->roles()->attach($financeRole);

    $this->regularUser = User::factory()->create(['name' => 'Regular User']);
    $this->regularUser->roles()->attach($userRole);
  }

  #[Test]
  public function admin_can_create_products()
  {
    $productData = [
      'name' => 'Test Product',
      'amount' => 1000, // R$ 10,00 em centavos
    ];

    $response = $this->actingAs($this->adminUser)
      ->postJson('/api/products', $productData);

    $response->assertStatus(201)
      ->assertJson([
        'name' => 'Test Product',
        'amount' => 1000,
      ]);

    $this->assertDatabaseHas('products', $productData);
  }

  #[Test]
  public function manager_can_create_products()
  {
    $productData = [
      'name' => 'Manager Product',
      'amount' => 2000,
    ];

    $response = $this->actingAs($this->managerUser)
      ->postJson('/api/products', $productData);

    $response->assertStatus(201);
    $this->assertDatabaseHas('products', $productData);
  }

  #[Test]
  public function finance_can_create_products()
  {
    $productData = [
      'name' => 'Finance Product',
      'amount' => 3000,
    ];

    $response = $this->actingAs($this->financeUser)
      ->postJson('/api/products', $productData);

    $response->assertStatus(201);
    $this->assertDatabaseHas('products', $productData);
  }

  #[Test]
  public function regular_user_cannot_create_products()
  {
    $productData = [
      'name' => 'Unauthorized Product',
      'amount' => 4000,
    ];

    $response = $this->actingAs($this->regularUser)
      ->postJson('/api/products', $productData);

    $response->assertStatus(403); // Forbidden
    $this->assertDatabaseMissing('products', $productData);
  }

  #[Test]
  public function any_authenticated_user_can_view_products()
  {
    // Criar alguns produtos para listar
    Product::create(['name' => 'Product 1', 'amount' => 1000]);
    Product::create(['name' => 'Product 2', 'amount' => 2000]);

    // Testar acesso para usuário regular
    $response = $this->actingAs($this->regularUser)
      ->getJson('/api/products');

    $response->assertStatus(200)
      ->assertJsonCount(2);
  }

  #[Test]
  public function admin_can_update_products()
  {
    $product = Product::create(['name' => 'Original Product', 'amount' => 1000]);

    $updateData = [
      'name' => 'Updated Product',
      'amount' => 1500,
    ];

    $response = $this->actingAs($this->adminUser)
      ->putJson("/api/products/{$product->id}", $updateData);

    $response->assertStatus(200)
      ->assertJson($updateData);

    $this->assertDatabaseHas('products', $updateData);
  }

  #[Test]
  public function admin_can_delete_products()
  {
    $product = Product::create(['name' => 'Product to Delete', 'amount' => 1000]);

    $response = $this->actingAs($this->adminUser)
      ->deleteJson("/api/products/{$product->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
  }
}
