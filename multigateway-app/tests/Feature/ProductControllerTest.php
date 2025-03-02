<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
  use DatabaseTransactions;

  protected $adminUser;
  protected $managerUser;
  protected $financeUser;
  protected $regularUser;
  protected $existingProduct;

  protected function setUp(): void
  {
    parent::setUp();

    // Usar usuários existentes do seed
    $this->adminUser = User::where('email', 'admin@example.com')->first();
    $this->managerUser = User::where('email', 'manager@example.com')->first();
    $this->financeUser = User::where('email', 'finance@example.com')->first();
    $this->regularUser = User::where('email', 'user@example.com')->first();

    // Usar um produto existente do seed para testes
    $this->existingProduct = Product::first();
  }

  #[Test]
  public function admin_can_create_products()
  {
    $productData = [
      'name' => 'Test Product Admin',
      'amount' => 1000, // R$ 10,00 em centavos
    ];

    $response = $this->actingAs($this->adminUser)
      ->postJson('/api/products', $productData);

    $response->assertStatus(201)
      ->assertJson([
        'name' => 'Test Product Admin',
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
    // Os produtos já existem do seed, não precisamos criar mais

    // Testar acesso para usuário regular
    $response = $this->actingAs($this->regularUser)
      ->getJson('/api/products');

    $response->assertStatus(200);
    // Deve haver pelo menos os 3 produtos do seed
    $this->assertGreaterThanOrEqual(3, count($response->json()));
  }

  #[Test]
  public function admin_can_update_products()
  {
    // Criar um produto específico para atualização
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
    // Criar um produto específico para exclusão
    $product = Product::create(['name' => 'Product to Delete', 'amount' => 1000]);

    $response = $this->actingAs($this->adminUser)
      ->deleteJson("/api/products/{$product->id}");

    $response->assertStatus(204);

    // Como estamos usando soft deletes, o produto ainda existe mas com deleted_at
    $this->assertSoftDeleted('products', ['id' => $product->id]);

    // Verificar se não é mais acessível via consultas normais
    $this->assertNull(Product::find($product->id));
  }
}
