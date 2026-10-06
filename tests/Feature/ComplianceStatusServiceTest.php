<?php

use App\Enums\ComplianceStatus;
use App\Models\ComplianceRecord;
use App\Models\ComplianceRequirement;
use App\Models\Employee;
use App\Services\ComplianceStatusService;
use Illuminate\Support\Carbon;

test('compliance status respects expiry boundaries and missing evidence', function () {
    $service = app(ComplianceStatusService::class);
    $requirement = ComplianceRequirement::factory()->create(['applies_to' => 'employee', 'default_reminder_days' => 30]);
    $employee = Employee::factory()->create();
    $today = Carbon::parse('2026-07-13');

    $valid = ComplianceRecord::factory()->for($requirement, 'requirement')->for($employee)->create(['expires_at' => '2026-08-13']);
    $soon = ComplianceRecord::factory()->for($requirement, 'requirement')->for($employee)->create(['expires_at' => '2026-08-12']);
    $expired = ComplianceRecord::factory()->for($requirement, 'requirement')->for($employee)->create(['expires_at' => '2026-07-12']);
    $missing = ComplianceRecord::factory()->for($requirement, 'requirement')->for($employee)->missing()->create();

    expect($service->statusFor($valid, $today))->toBe(ComplianceStatus::Valid)
        ->and($service->statusFor($soon, $today))->toBe(ComplianceStatus::ExpiringSoon)
        ->and($service->statusFor($expired, $today))->toBe(ComplianceStatus::Expired)
        ->and($service->statusFor($missing, $today))->toBe(ComplianceStatus::Missing);
});
