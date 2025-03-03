<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'gateway_id' => Gateway::factory(),
            'external_id' => 'tx-' . $this->faker->uuid(),
            'status' => 'COMPLETED',
            'amount' => $this->faker->numberBetween(1000, 50000),
            'card_last_numbers' => $this->faker->numerify('####'),
        ];
    }

    /**
     * Indicate that the transaction is refunded.
     */
    public function refunded(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'REFUNDED',
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'PENDING',
        ]);
    }
}
