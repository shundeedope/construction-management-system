<?php

use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Widgets\OverdueInvoicesReport;
use App\Filament\Widgets\ProjectsByStatusReport;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('overdue invoice report scopes financial rows to the assigned project', function () {
    $visibleProject = Project::factory()->create();
    $hiddenProject = Project::factory()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $visibleProject, ProjectRole::ProjectManager, canViewFinancials: true);
    $visibleContract = Contract::factory()->for($visibleProject)->create(['client_id' => $visibleProject->client_id, 'status' => ContractStatus::Executed]);
    $hiddenContract = Contract::factory()->for($hiddenProject)->create(['client_id' => $hiddenProject->client_id, 'status' => ContractStatus::Executed]);
    $visible = Invoice::factory()->for($visibleProject)->for($visibleContract)->create(['number' => 'INV-VISIBLE', 'status' => InvoiceStatus::Sent, 'due_date' => today()->subDay()]);
    $hidden = Invoice::factory()->for($hiddenProject)->for($hiddenContract)->create(['number' => 'INV-HIDDEN', 'status' => InvoiceStatus::Sent, 'due_date' => today()->subDay()]);
    $void = Invoice::factory()->for($visibleProject)->for($visibleContract)->create(['number' => 'INV-VOID', 'status' => InvoiceStatus::Void, 'due_date' => today()->subDay()]);
    $this->actingAs($manager);

    Livewire::test(OverdueInvoicesReport::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden, $void]);
});

test('projects by status report supports status filters within a user scope', function () {
    $activeProject = Project::factory()->active()->create();
    $planningProject = Project::factory()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $activeProject, ProjectRole::ProjectManager);
    assignToProject($manager, $planningProject, ProjectRole::ProjectManager);
    $this->actingAs($manager);

    Livewire::test(ProjectsByStatusReport::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$activeProject])
        ->assertCanNotSeeTableRecords([$planningProject]);
});
