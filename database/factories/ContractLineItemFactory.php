<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractLineItem>
 */
class ContractLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'cost_code' => fake()->optional()->numerify('##-###'),
            'description' => fake()->sentence(4),
            'quantity' => fake()->randomFloat(3, 1, 100),
            'unit' => fake()->randomElement(['ls', 'ea', 'hr', 'sq ft']),
            'unit_price' => fake()->numberBetween(100, 50_000),
            'amount' => fake()->numberBetween(10_000, 100_000_00),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
