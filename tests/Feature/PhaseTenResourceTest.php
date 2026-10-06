<?php

use App\Enums\Role;
use App\Filament\Pages\WeeklyTimesheet;
use App\Filament\Resources\ComplianceRecords\Pages\ListComplianceRecords;
use App\Filament\Resources\ComplianceRequirements\Pages\ListComplianceRequirements;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\LeaveTransactions\Pages\ListLeaveTransactions;
use App\Filament\Resources\TimeEntries\Pages\ListTimeEntries;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('phase ten resources load for an authorized administrator', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole(Role::Administrator));

    foreach ([ListEmployees::class, ListTimeEntries::class, ListLeaveTransactions::class, ListComplianceRequirements::class, ListComplianceRecords::class, WeeklyTimesheet::class] as $component) {
        Livewire::test($component)->assertOk();
    }
});
