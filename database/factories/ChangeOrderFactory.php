<?php

namespace Database\Factories;

use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeOrder>
 */
class ChangeOrderFactory extends Factory
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
            'type' => 'owner',
            'number' => fake()->unique()->bothify('CO-####'),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => 'draft',
            'requested_at' => now(),
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'schedule_impact_days' => 0,
            'created_by' => User::factory(),
            'approved_by' => null,
            'rejection_reason' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'submitted', 'submitted_at' => now()]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'approved', 'submitted_at' => now()->subDay(), 'approved_at' => now(), 'approved_by' => User::factory()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'rejected', 'submitted_at' => now()->subDay(), 'rejected_at' => now(), 'rejection_reason' => 'Needs additional documentation.']);
    }

    public function subcontract(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'subcontract']);
    }
}
