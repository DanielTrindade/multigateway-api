<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['ADMIN', 'MANAGER', 'FINANCE', 'USER']),
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Indicate that the role is admin.
     */
    public function admin(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'ADMIN',
            'description' => 'Acesso completo ao sistema',
        ]);
    }

    /**
     * Indicate that the role is manager.
     */
    public function manager(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'MANAGER',
            'description' => 'Gerenciamento de produtos e usuários',
        ]);
    }

    /**
     * Indicate that the role is finance.
     */
    public function finance(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'FINANCE',
            'description' => 'Gerenciamento de produtos e reembolsos',
        ]);
    }

    /**
     * Indicate that the role is user.
     */
    public function user(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'USER',
            'description' => 'Acesso básico ao sistema',
        ]);
    }
}
