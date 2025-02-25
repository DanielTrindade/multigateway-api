<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationControllerTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function user_can_login_with_correct_credentials()
  {
    // Criar role de usuário padrão
    $userRole = Role::create(['name' => 'USER', 'description' => 'Regular User']);

    // Criar usuário
    $user = User::factory()->create([
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
    ]);

    // Atribuir role
    $user->roles()->attach($userRole);

    // Tentar login com credenciais corretas
    $response = $this->postJson('/api/login', [
      'email' => 'test@example.com',
      'password' => 'password',
    ]);

    // Verificar resposta
    $response->assertStatus(200)
      ->assertJsonStructure([
        'user',
        'token'
      ]);
  }

  #[Test]
  public function user_cannot_login_with_incorrect_credentials()
  {
    // Criar usuário
    User::factory()->create([
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
    ]);

    // Tentar login com senha incorreta
    $response = $this->postJson('/api/login', [
      'email' => 'test@example.com',
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
    // Criar role de usuário padrão (necessário para o registro)
    Role::create(['name' => 'USER', 'description' => 'Regular User']);

    // Dados para registro
    $userData = [
      'name' => 'John Doe',
      'email' => 'john@example.com',
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
          'roles'
        ],
        'token'
      ]);

    // Verificar se o usuário existe no banco
    $this->assertDatabaseHas('users', [
      'name' => 'John Doe',
      'email' => 'john@example.com',
    ]);

    // Verificar se a role USER foi atribuída
    $user = User::where('email', 'john@example.com')->first();
    $this->assertTrue($user->hasRole('USER'));
  }
}
