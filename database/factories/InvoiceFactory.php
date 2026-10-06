<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'contract_id' => Contract::factory(),
            'type' => 'client',
            'number' => fake()->unique()->bothify('INV-####'),
            'status' => 'draft',
            'billing_period_start' => now()->subMonth()->toDateString(),
            'billing_period_end' => now()->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => fake()->numberBetween(10_000_00, 100_000_00),
            'retention_amount' => 0,
            'total' => fake()->numberBetween(10_000_00, 100_000_00),
            'sent_at' => null,
            'approved_at' => null,
            'voided_at' => null,
            'created_by' => User::factory(),
            'approved_by' => null,
            'rejection_reason' => null,
            'void_reason' => null,
            'notes' => null,
        ];
    }

    public function subcontractor(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'subcontractor', 'number' => fake()->unique()->bothify('VINV-####')]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'approved', 'approved_at' => now(), 'approved_by' => User::factory()]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'paid', 'approved_at' => now()->subDays(2), 'sent_at' => now()->subDays(2)]);
    }
}
