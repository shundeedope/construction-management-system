<?php

namespace Database\Factories;

use App\Models\ChangeOrder;
use App\Models\ChangeOrderLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeOrderLineItem>
 */
class ChangeOrderLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'change_order_id' => ChangeOrder::factory(),
            'contract_line_item_id' => null,
            'cost_code' => fake()->optional()->numerify('##-###'),
            'description' => fake()->sentence(4),
            'amount' => fake()->numberBetween(-50_000_00, 100_000_00),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
