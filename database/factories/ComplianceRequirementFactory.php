<?php

namespace Database\Factories;

use App\Models\ComplianceRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceRequirement>
 */
class ComplianceRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'applies_to' => 'employee',
            'is_required' => true,
            'default_reminder_days' => 30,
            'is_active' => true,
        ];
    }
}
