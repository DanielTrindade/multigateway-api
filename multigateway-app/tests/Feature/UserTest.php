<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_have_roles()
    {
        // Criar roles de teste
        $adminRole = Role::create(['name' => 'ADMIN', 'description' => 'Administrator']);
        $userRole = Role::create(['name' => 'USER', 'description' => 'Regular User']);

        // Criar usuário
        $user = User::factory()->create();

        // Atribuir roles
        $user->roles()->attach([$adminRole->id, $userRole->id]);

        // Verificar se o usuário tem as roles esperadas
        $this->assertTrue($user->hasRole('ADMIN'));
        $this->assertTrue($user->hasRole('USER'));
        $this->assertFalse($user->hasRole('MANAGER'));

        // Verificar funções hasAnyRole e hasAllRoles
        $this->assertTrue($user->hasAnyRole(['ADMIN', 'MANAGER']));
        $this->assertTrue($user->hasAllRoles(['ADMIN', 'USER']));
        $this->assertFalse($user->hasAllRoles(['ADMIN', 'USER', 'MANAGER']));
    }
}
