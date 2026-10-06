<?php

namespace Database\Factories;

use App\Models\ComplianceRecord;
use App\Models\ComplianceRequirement;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceRecord>
 */
class ComplianceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compliance_requirement_id' => ComplianceRequirement::factory(),
            'employee_id' => Employee::factory(),
            'vendor_id' => null,
            'project_id' => null,
            'document_id' => null,
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'status' => 'valid',
            'notes' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->addDays(15)->toDateString(), 'status' => 'expiring_soon']);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subDay()->toDateString(), 'status' => 'expired']);
    }

    public function missing(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => null, 'status' => 'missing']);
    }
}
