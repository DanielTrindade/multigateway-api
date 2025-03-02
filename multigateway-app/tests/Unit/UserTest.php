<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function user_can_have_roles()
    {
        // Usar roles já existentes do seed
        $adminRole = Role::where('name', 'ADMIN')->first();
        $userRole = Role::where('name', 'USER')->first();
        $managerRole = Role::where('name', 'MANAGER')->first();

        // Criar usuário para teste
        $user = User::factory()->create([
            'name' => 'Test User For Roles',
            'email' => 'testroles@example.com'
        ]);

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

    #[Test]
    public function user_roles_can_be_updated()
    {
        // Usar roles existentes do seed
        $adminRole = Role::where('name', 'ADMIN')->first();
        $managerRole = Role::where('name', 'MANAGER')->first();
        $financeRole = Role::where('name', 'FINANCE')->first();
        $userRole = Role::where('name', 'USER')->first();

        // Criar usuário para teste
        $user = User::factory()->create([
            'name' => 'Role Update Test User',
            'email' => 'roleupdatetests@example.com'
        ]);

        // Atribuir role inicial
        $user->roles()->attach($userRole);
        $this->assertTrue($user->hasRole('USER'));
        $this->assertFalse($user->hasRole('ADMIN'));

        // Atualizar roles
        $user->roles()->sync([$adminRole->id, $managerRole->id]);

        // Recarregar modelo para garantir que estamos vendo dados atualizados
        $user->refresh();

        // Verificar se as roles foram atualizadas corretamente
        $this->assertTrue($user->hasRole('ADMIN'));
        $this->assertTrue($user->hasRole('MANAGER'));
        $this->assertFalse($user->hasRole('USER'));
        $this->assertFalse($user->hasRole('FINANCE'));
    }
}
