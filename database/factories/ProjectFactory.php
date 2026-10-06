<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_number' => fake()->unique()->bothify('JOB-2026-####'),
            'name' => fake()->sentence(3),
            'client_id' => Client::factory(),
            'project_manager_id' => User::factory(),
            'description' => fake()->optional()->paragraph(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => 'TX',
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'start_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'expected_completion_date' => fake()->dateTimeBetween('+2 months', '+2 years')->format('Y-m-d'),
            'actual_completion_date' => null,
            'status' => 'planning',
            'internal_notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'active']);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'completed', 'actual_completion_date' => now()->toDateString()]);
    }

    public function unassigned(): static
    {
        return $this->state(fn (array $attributes): array => ['project_manager_id' => null]);
    }
}
