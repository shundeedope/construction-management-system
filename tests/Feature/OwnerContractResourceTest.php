<?php

use App\Enums\ContractStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\OwnerContracts\Pages\CreateOwnerContract;
use App\Filament\Resources\OwnerContracts\Pages\ListOwnerContracts;
use App\Filament\Resources\OwnerContracts\Pages\ViewOwnerContract;
use App\Models\Contract;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->project = Project::factory()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->actingAs($this->manager);
});

test('an authorized manager can create an owner contract with Schedule of Values totals', function () {
    Livewire::test(CreateOwnerContract::class)
        ->fillForm([
            'number' => 'OC-1001',
            'title' => 'Owner agreement',
            'project_id' => $this->project->id,
            'client_id' => $this->project->client_id,
            'retention_basis_points' => 500,
            'lineItems' => [[
                'description' => 'General conditions',
                'amount' => '1000.50',
                'sort_order' => 0,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $contract = Contract::query()->where('number', 'OC-1001')->firstOrFail();

    expect($contract->project_id)->toBe($this->project->id)
        ->and($contract->client_id)->toBe($this->project->client_id)
        ->and($contract->original_amount)->toBe(100_050);
});

test('the owner-contract table excludes downstream commitments', function () {
    $owner = Contract::factory()->for($this->project)->create(['client_id' => $this->project->client_id]);
    $subcontract = Contract::factory()->subcontract()->for($this->project)->create();

    Livewire::test(ListOwnerContracts::class)
        ->assertCanSeeTableRecords([$owner])
        ->assertCanNotSeeTableRecords([$subcontract]);
});

test('an executed owner contract has a read-only overview page', function () {
    $contract = Contract::factory()->for($this->project)->create([
        'client_id' => $this->project->client_id,
        'status' => ContractStatus::Executed,
    ]);

    Livewire::test(ViewOwnerContract::class, ['record' => $contract->getKey()])
        ->assertOk()
        ->assertSee('Contract overview')
        ->assertSee('Schedule of Values')
        ->assertSee('Financial terms are locked');
});

test('a pending owner contract can be executed straight from the list', function () {
    $contract = Contract::factory()->for($this->project)->create([
        'client_id' => $this->project->client_id,
        'status' => ContractStatus::PendingApproval,
    ]);

    Livewire::test(ListOwnerContracts::class)
        ->callAction(TestAction::make('execute')->table($contract))
        ->assertNotified();

    expect($contract->fresh()->status)->toBe(ContractStatus::Executed);
});
