<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Pages\OperationalReports;
use App\Filament\Widgets\OverdueInvoicesReport;
use App\Filament\Widgets\UpcomingComplianceReport;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('confidential reports are inaccessible to employees without financial or compliance access', function () {
    $employee = assignToProject(userWithRole(Role::Employee), Project::factory()->create(), ProjectRole::Employee);
    $this->actingAs($employee);

    expect(OperationalReports::canAccess())->toBeTrue()
        ->and(OverdueInvoicesReport::canView())->toBeFalse()
        ->and(UpcomingComplianceReport::canView())->toBeFalse();
});
