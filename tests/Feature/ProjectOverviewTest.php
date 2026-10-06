<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ContractStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\ChangeOrderWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('the project overview renders zero-state KPIs for authorized financial users', function () {
    $project = Project::factory()->create();
    $manager = assignToProject(
        userWithRole(Role::ProjectManager),
        $project,
        ProjectRole::ProjectManager,
        canViewFinancials: true,
    );
    $this->actingAs($manager);

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->assertOk()
        ->assertSee('Original owner value')
        ->assertSee('$0.00')
        ->assertSee('Team')
        ->assertSee('Activity')
        ->assertSee('Contracts')
        ->assertSee('Users');
});

test('the overview does not render financial sections for project users without financial access', function () {
    $project = Project::factory()->create();
    $supervisor = assignToProject(userWithRole(Role::SiteSupervisor), $project, ProjectRole::SiteSupervisor);
    $this->actingAs($supervisor);

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->assertOk()
        ->assertDontSee('Original owner value')
        ->assertSee('Team');
});

test('the project overview shows only committed owner value and commitments', function () {
    $project = Project::factory()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed, 'original_amount' => 500_000_00]);
    Contract::factory()->subcontract()->for($project)->create(['status' => ContractStatus::Executed, 'original_amount' => 185_000_00]);
    Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'original_amount' => 10_000_00]);
    $this->actingAs($manager);

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->assertSee('Original owner value')
        ->assertSee('$500,000.00')
        ->assertSee('Original commitments')
        ->assertSee('$185,000.00');
});

test('the project overview displays approved change values separately from original values', function () {
    $project = Project::factory()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed]);
    $changeOrder = ChangeOrder::factory()->for($project)->for($contract)->create(['status' => ChangeOrderStatus::Approved]);
    $changeOrder->lineItems()->create(['description' => 'Approved work', 'amount' => 12_500, 'sort_order' => 1]);
    $this->actingAs($manager);

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->assertSee('Approved owner changes')
        ->assertSee('$125.00');
});

test('the project overview summarizes labor for users allowed to view all time entries', function () {
    $project = Project::factory()->create();
    $administrator = userWithRole(Role::Administrator);
    TimeEntry::factory()->for($project)->approved()->create(['regular_hours' => 8, 'overtime_hours' => 1.5]);
    TimeEntry::factory()->for($project)->create(['regular_hours' => 2, 'overtime_hours' => 0.5]);
    $this->actingAs($administrator);

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->assertSee('Labor')
        ->assertSee('Regular hours')
        ->assertSee('10.00')
        ->assertSee('2.00')
        ->assertSee('9.50');
});

test('the command center shows team members, workflow activity, and quick actions', function () {
    $project = Project::factory()->active()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed, 'original_amount' => 100_00]);
    $changeOrder = ChangeOrder::factory()->for($project)->for($contract)->create();
    $changeOrder->lineItems()->create(['description' => 'Extra work', 'amount' => 10_00, 'sort_order' => 0]);
    $this->actingAs($manager);
    app(ChangeOrderWorkflowService::class)->submit($changeOrder, $manager);

    Livewire::test(ViewProject::class, ['record' => $project->getKey()])
        ->assertSee('Financial health')
        ->assertSee('Billed to client')
        ->assertSee($manager->name)
        ->assertSee('Change Order')
        ->assertSee('submitted')
        ->assertSee('New change order')
        ->assertSee('New client invoice');
});
