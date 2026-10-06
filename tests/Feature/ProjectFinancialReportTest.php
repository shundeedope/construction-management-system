<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ContractStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Widgets\LowestMarginProjectsReport;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ProjectFinancialReportQuery;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('financial report aggregates revised values and orders projects by lowest margin', function () {
    $lowMarginProject = Project::factory()->create(['job_number' => 'JOB-LOW']);
    $highMarginProject = Project::factory()->create(['job_number' => 'JOB-HIGH']);
    $manager = assignToProject(userWithRole(Role::ProjectManager), $lowMarginProject, ProjectRole::ProjectManager, canViewFinancials: true);
    assignToProject($manager, $highMarginProject, ProjectRole::ProjectManager, canViewFinancials: true);

    $lowOwnerContract = Contract::factory()->for($lowMarginProject)->create(['client_id' => $lowMarginProject->client_id, 'status' => ContractStatus::Executed, 'original_amount' => 100_000_00]);
    Contract::factory()->subcontract()->for($lowMarginProject)->create(['status' => ContractStatus::Executed, 'original_amount' => 90_000_00]);
    $ownerChange = ChangeOrder::factory()->for($lowMarginProject)->for($lowOwnerContract)->create(['status' => ChangeOrderStatus::Approved]);
    $ownerChange->lineItems()->create(['description' => 'Owner change', 'amount' => 10_000_00, 'sort_order' => 1]);

    Contract::factory()->for($highMarginProject)->create(['client_id' => $highMarginProject->client_id, 'status' => ContractStatus::Executed, 'original_amount' => 100_000_00]);
    Contract::factory()->subcontract()->for($highMarginProject)->create(['status' => ContractStatus::Executed, 'original_amount' => 30_000_00]);
    $this->actingAs($manager);

    $summary = app(ProjectFinancialReportQuery::class)->apply(Project::query()->whereKey($lowMarginProject))->firstOrFail();

    expect($summary->revised_owner_value)->toBe(110_000_00)
        ->and($summary->revised_commitments)->toBe(90_000_00)
        ->and($summary->gross_margin)->toBe(20_000_00);

    Livewire::test(LowestMarginProjectsReport::class)
        ->assertCanSeeTableRecords([$lowMarginProject, $highMarginProject])
        ->assertSeeInOrder(['JOB-LOW', 'JOB-HIGH']);

    Livewire::test(ListProjects::class)
        ->assertCanSeeTableRecords([$lowMarginProject, $highMarginProject])
        ->assertSee('$110,000.00')
        ->assertSee('$90,000.00');
});
