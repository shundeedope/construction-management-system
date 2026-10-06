<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Widgets\AttentionOverview;
use App\Filament\Widgets\FinancialOverview;
use App\Filament\Widgets\ProjectsByStatus;
use App\Models\Project;
use App\Services\DashboardMetricsService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('financial widgets are absent for users without financial access', function () {
    $project = Project::factory()->create();
    $employee = assignToProject(userWithRole(Role::Employee), $project, ProjectRole::Employee);
    $this->actingAs($employee);

    expect(FinancialOverview::canView())->toBeFalse()
        ->and(AttentionOverview::canView())->toBeTrue()
        ->and(ProjectsByStatus::canView())->toBeTrue();
});

test('financial widgets remain hidden when a project assignment lacks financial access', function () {
    $project = Project::factory()->create();
    $supervisor = assignToProject(userWithRole(Role::SiteSupervisor), $project, ProjectRole::SiteSupervisor);
    $this->actingAs($supervisor);

    expect(FinancialOverview::canView())->toBeTrue();
    expect(app(DashboardMetricsService::class)->financial($supervisor)['revised_value']->cents)->toBe(0);
});
