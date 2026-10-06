<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
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
            'client_id' => Client::factory(),
            'vendor_id' => null,
            'type' => 'owner',
            'number' => fake()->unique()->bothify('OC-####'),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => 'draft',
            'original_amount' => fake()->numberBetween(100_000_00, 1_000_000_00),
            'retention_basis_points' => 500,
            'executed_at' => null,
            'start_date' => now()->toDateString(),
            'completion_date' => now()->addYear()->toDateString(),
            'created_by' => User::factory(),
            'approved_by' => null,
        ];
    }

    public function subcontract(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'subcontract', 'client_id' => null, 'vendor_id' => Vendor::factory(), 'number' => fake()->unique()->bothify('SC-####')]);
    }

    public function purchaseOrder(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'purchase_order', 'client_id' => null, 'vendor_id' => Vendor::factory(), 'number' => fake()->unique()->bothify('PO-####')]);
    }

    public function executed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'executed', 'executed_at' => now(), 'approved_by' => User::factory()]);
    }
}
