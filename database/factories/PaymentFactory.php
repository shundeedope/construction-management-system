<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'project_id' => Project::factory(),
            'direction' => 'incoming',
            'amount' => fake()->numberBetween(1_00, 50_000_00),
            'payment_date' => now()->toDateString(),
            'method' => fake()->randomElement(['check', 'ach', 'wire']),
            'reference' => fake()->optional()->bothify('PAY-####'),
            'notes' => null,
            'recorded_by' => User::factory(),
        ];
    }

    public function outgoing(): static
    {
        return $this->state(fn (array $attributes): array => ['direction' => 'outgoing']);
    }
}
