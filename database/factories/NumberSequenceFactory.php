<?php

namespace Database\Factories;

use App\Models\NumberSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberSequence>
 */
class NumberSequenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->randomElement(['project', 'contract', 'invoice']),
            'scope' => 'global',
            'format' => 'JOB-{YEAR}-{SEQUENCE}',
            'next_value' => 1,
            'padding' => 4,
            'reset_frequency' => 'never',
            'current_period' => 'all',
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes): array => ['reset_frequency' => 'yearly', 'current_period' => now()->year]);
    }
}
