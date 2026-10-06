<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->unique()->company(),
            'vendor_type' => fake()->randomElement(['subcontractor', 'supplier']),
            'primary_contact_name' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => 'TX',
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'tax_identifier' => fake()->optional()->numerify('##-#######'),
            'insurance_expires_at' => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function expiredInsurance(): static
    {
        return $this->state(fn (array $attributes): array => ['insurance_expires_at' => now()->subDay()->toDateString()]);
    }
}
