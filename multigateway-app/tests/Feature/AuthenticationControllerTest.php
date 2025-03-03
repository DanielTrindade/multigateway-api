<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $adminRole;
    protected $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Obter as roles para usar nos testes
        $this->adminRole = Role::where('name', 'ADMIN')->first();
        $this->userRole = Role::where('name', 'USER')->first();
    }

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
    public function user_can_register_without_specifying_roles()
    {
        // Dados para registro sem especificar roles
        $userData = [
            'name' => 'Default Role User',
            'email' => 'defaultrole@example.com',
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
            'name' => 'Default Role User',
            'email' => 'defaultrole@example.com',
        ]);

        // Verificar se a role USER foi atribuída automaticamente
        $user = User::where('email', 'defaultrole@example.com')->first();
        $this->assertTrue($user->hasRole('USER'));
        $this->assertFalse($user->hasRole('ADMIN'));
    }

    #[Test]
    public function user_can_register_with_specific_roles()
    {
        // Dados para registro com roles específicas
        $userData = [
            'name' => 'Admin Role User',
            'email' => 'adminrole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['ADMIN']
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
            'name' => 'Admin Role User',
            'email' => 'adminrole@example.com',
        ]);

        // Verificar se a role ADMIN foi atribuída
        $user = User::where('email', 'adminrole@example.com')->first();
        $this->assertTrue($user->hasRole('ADMIN'));
        $this->assertFalse($user->hasRole('USER')); // Não deve ter a role USER automaticamente
    }

    #[Test]
    public function user_can_register_with_multiple_roles()
    {
        // Dados para registro com múltiplas roles
        $userData = [
            'name' => 'Multi Role User',
            'email' => 'multirole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['ADMIN', 'MANAGER']
        ];

        // Enviar requisição de registro
        $response = $this->postJson('/api/register', $userData);

        // Verificar resposta
        $response->assertStatus(201);

        // Verificar se o usuário existe no banco
        $this->assertDatabaseHas('users', [
            'name' => 'Multi Role User',
            'email' => 'multirole@example.com',
        ]);

        // Verificar se ambas as roles foram atribuídas
        $user = User::where('email', 'multirole@example.com')->first();
        $this->assertTrue($user->hasRole('ADMIN'));
        $this->assertTrue($user->hasRole('MANAGER'));
        $this->assertFalse($user->hasRole('USER')); // Não deve ter a role USER automaticamente

        // Verificar se o user tem todas as roles esperadas
        $this->assertTrue($user->hasAllRoles(['ADMIN', 'MANAGER']));
    }

    #[Test]
    public function user_cannot_register_with_invalid_roles()
    {
        // Dados para registro com role inválida
        $userData = [
            'name' => 'Invalid Role User',
            'email' => 'invalidrole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['INVALID_ROLE']
        ];

        // Enviar requisição de registro
        $response = $this->postJson('/api/register', $userData);

        // Deve falhar com erro de validação
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['roles.0']);

        // Verificar que o usuário não foi criado
        $this->assertDatabaseMissing('users', [
            'email' => 'invalidrole@example.com',
        ]);
    }
}
