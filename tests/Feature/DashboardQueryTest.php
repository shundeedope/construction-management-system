<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Widgets\AttentionOverview;
use App\Filament\Widgets\FinancialOverview;
use App\Filament\Widgets\ProjectsByStatus;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('financial widget uses a bounded number of aggregate queries', function () {
    $manager = userWithRole(Role::ProjectManager);

    foreach (range(1, 8) as $number) {
        $project = Project::factory()->create();
        assignToProject($manager, $project, ProjectRole::ProjectManager, canViewFinancials: true);
    }

    $this->actingAs($manager);
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(FinancialOverview::class)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(14);

    DB::disableQueryLog();
});

test('attention and status widgets use bounded query counts', function () {
    $manager = userWithRole(Role::ProjectManager);

    foreach (range(1, 8) as $number) {
        $project = Project::factory()->active()->create();
        assignToProject($manager, $project, ProjectRole::ProjectManager, canViewFinancials: true);
    }

    $this->actingAs($manager);
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(AttentionOverview::class)->assertOk();
    Livewire::test(ProjectsByStatus::class)->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(12);

    DB::disableQueryLog();
});
