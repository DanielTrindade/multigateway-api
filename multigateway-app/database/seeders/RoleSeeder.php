<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $roles = [
            ['name' => 'ADMIN', 'description' => 'Acesso completo ao sistema'],
            ['name' => 'MANAGER', 'description' => 'Gerenciamento de produtos e usuários'],
            ['name' => 'FINANCE', 'description' => 'Gerenciamento de produtos e reembolsos'],
            ['name' => 'USER', 'description' => 'Acesso básico ao sistema'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
