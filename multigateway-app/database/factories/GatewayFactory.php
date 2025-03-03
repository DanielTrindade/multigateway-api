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
        $gatewayTypes = ['gateway1', 'gateway2', 'gateway3'];
        $index = $this->faker->numberBetween(0, 2);

        return [
            'name' => 'Gateway ' . ($index + 1),
            'type' => $gatewayTypes[$index],
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
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set specific gateway type.
     */
    public function withType(string $type): Factory
    {
        return $this->state(fn(array $attributes) => [
            'type' => $type,
        ]);
    }
}
