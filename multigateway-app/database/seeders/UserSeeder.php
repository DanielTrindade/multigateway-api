<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Obter todas as roles
    $adminRole = Role::where('name', 'ADMIN')->first();
    $managerRole = Role::where('name', 'MANAGER')->first();
    $financeRole = Role::where('name', 'FINANCE')->first();
    $userRole = Role::where('name', 'USER')->first();

    // Criar usuário admin
    $admin = User::create([
      'name' => 'Admin User',
      'email' => 'admin@example.com',
      'password' => Hash::make('password'),
    ]);
    $admin->roles()->attach($adminRole);

    // Criar outros usuários de exemplo
    $manager = User::create([
      'name' => 'Manager User',
      'email' => 'manager@example.com',
      'password' => Hash::make('password'),
    ]);
    $manager->roles()->attach($managerRole);

    $finance = User::create([
      'name' => 'Finance User',
      'email' => 'finance@example.com',
      'password' => Hash::make('password'),
    ]);
    $finance->roles()->attach($financeRole);

    $regularUser = User::create([
      'name' => 'Regular User',
      'email' => 'user@example.com',
      'password' => Hash::make('password'),
    ]);
    $regularUser->roles()->attach($userRole);
  }
}
