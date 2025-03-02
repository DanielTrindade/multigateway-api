<?php

namespace Database\Factories;

use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gateway>
 */
class GatewayFactory extends Factory
{
    protected $model = Gateway::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Gateway 1', 'Gateway 2', 'Gateway 3']),
            'is_active' => true,
            'priority' => $this->faker->numberBetween(1, 10),
            'credentials' => json_encode([
                'api_key' => $this->faker->uuid(),
                'api_secret' => $this->faker->sha256()
            ])
        ];
    }

    /**
     * Indicate that the gateway is inactive.
     */
    public function inactive(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
