<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveTransaction>
 */
class LeaveTransactionFactory extends Factory
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
            'type' => 'accrual',
            'hours' => 8,
            'effective_date' => now()->toDateString(),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function usage(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'usage', 'hours' => -8]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'adjustment']);
    }
}
