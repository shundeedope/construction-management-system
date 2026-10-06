<?php

use App\Enums\LeaveTransactionType;
use App\Enums\Role;
use App\Models\Employee;
use App\Services\LeaveBalanceService;
use Database\Seeders\RolesAndPermissionsSeeder;

test('the leave balance is an append-only sum of accruals, usage, and signed adjustments', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $administrator = userWithRole(Role::Administrator);
    $employee = Employee::factory()->create();
    $service = app(LeaveBalanceService::class);

    $service->record($employee, LeaveTransactionType::Accrual, 16, '2026-07-01', null, $administrator);
    $service->record($employee, LeaveTransactionType::Usage, 8, '2026-07-02', null, $administrator);
    $service->record($employee, LeaveTransactionType::Adjustment, -2, '2026-07-03', 'Correction', $administrator);

    expect($service->balanceFor($employee))->toBe(6.0)
        ->and($employee->leaveTransactions()->count())->toBe(3);
});
