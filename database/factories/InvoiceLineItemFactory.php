<?php

namespace Database\Factories;

use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLineItem>
 */
class InvoiceLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'contract_line_item_id' => ContractLineItem::factory(),
            'scheduled_value' => fake()->numberBetween(10_000_00, 100_000_00),
            'previously_billed' => 0,
            'work_completed_this_period' => fake()->numberBetween(1_000_00, 10_000_00),
            'materials_stored' => 0,
            'retention_amount' => 0,
            'current_due' => fake()->numberBetween(1_000_00, 10_000_00),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
