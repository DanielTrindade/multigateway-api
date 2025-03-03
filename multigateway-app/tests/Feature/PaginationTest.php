<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PaginationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_paginates_products_list()
    {
        // Criar 25 produtos (mais do que nossa paginação de 20)
        Product::factory()->count(25)->create();

        // Autenticar como admin
        $admin = User::where('email', 'admin@example.com')->first();

        // Fazer request para listar produtos
        $response = $this->actingAs($admin)
            ->getJson('/api/products');

        // Verificar status e estrutura da resposta
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'links',
                    'path',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);
        // Verificar que apenas 20 itens são retornados (nossa paginação)
        $this->assertCount(20, $response->json('data'));

        // Verificar que o total é 25 (todos os produtos)
        $this->assertEquals(28, $response->json('meta.total'));

        // Acessar a segunda página
        $response2 = $this->actingAs($admin)
            ->getJson('/api/products?page=2');

        // Verificar que a segunda página tem 5 produtos
        $this->assertCount(8, $response2->json('data'));
        $this->assertEquals(2, $response2->json('meta.current_page'));
    }
}
