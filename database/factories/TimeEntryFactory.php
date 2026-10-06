<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'project_id' => Project::factory(),
            'work_date' => now()->subDay()->toDateString(),
            'regular_hours' => 8,
            'overtime_hours' => 0,
            'is_billable' => true,
            'description' => fake()->sentence(),
            'status' => 'draft',
            'submitted_at' => null,
            'approved_at' => null,
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
        return $this->state(fn (array $attributes): array => ['status' => 'approved', 'submitted_at' => now()->subDay(), 'approved_at' => now()]);
    }
}
