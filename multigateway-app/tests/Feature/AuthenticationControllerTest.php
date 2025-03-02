<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationControllerTest extends TestCase
{
    use DatabaseTransactions; // Transação para isolar os testes sem limpar o banco

    #[Test]
    public function user_can_login_with_correct_credentials()
    {
        // Usar usuário existente do seed em vez de criar novo
        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com', // Usuario do seed
            'password' => 'password',      // Senha padrão do seed
        ]);

        // Verificar resposta
        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                    'deleted_at'
                ],
                'token'
            ]);
    }

    #[Test]
    public function user_cannot_login_with_incorrect_credentials()
    {
        // Usar usuário existente do seed
        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        // Verificar resposta
        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Credenciais inválidas'
            ]);
    }

    #[Test]
    public function user_can_register()
    {
        // Dados para registro (não precisamos criar a role, pois já existe no seed)
        $userData = [
            'name' => 'New Test User',
            'email' => 'newtest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Enviar requisição de registro
        $response = $this->postJson('/api/register', $userData);
        // Verificar resposta
        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                    'roles'
                ],
                'token'
            ]);

        // Verificar se o usuário existe no banco
        $this->assertDatabaseHas('users', [
            'name' => 'New Test User',
            'email' => 'newtest@example.com',
        ]);

        // Verificar se a role USER foi atribuída
        $user = User::where('email', 'newtest@example.com')->first();
        $this->assertTrue($user->hasRole('USER'));
    }
}
