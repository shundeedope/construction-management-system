<?php

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\Subcontracts\Pages\CreateSubcontract;
use App\Filament\Resources\Subcontracts\Pages\ListSubcontracts;
use App\Filament\Resources\Subcontracts\Pages\ViewSubcontract;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->project = Project::factory()->create();
    $this->vendor = Vendor::factory()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->actingAs($this->manager);
});

test('an authorized manager can create a subcontract with Schedule of Values totals', function () {
    Livewire::test(CreateSubcontract::class)
        ->fillForm([
            'type' => ContractType::Subcontract->value,
            'number' => 'SC-1001',
            'title' => 'Electrical subcontract',
            'project_id' => $this->project->id,
            'vendor_id' => $this->vendor->id,
            'retention_basis_points' => 1_000,
            'lineItems' => [[
                'description' => 'Electrical work',
                'amount' => '2200.00',
                'sort_order' => 0,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $contract = Contract::query()->where('number', 'SC-1001')->firstOrFail();

    expect($contract->type)->toBe(ContractType::Subcontract)
        ->and($contract->vendor_id)->toBe($this->vendor->id)
        ->and($contract->client_id)->toBeNull()
        ->and($contract->original_amount)->toBe(220_000);
});

test('the subcontract table excludes owner contracts', function () {
    $owner = Contract::factory()->for($this->project)->create(['client_id' => $this->project->client_id]);
    $subcontract = Contract::factory()->subcontract()->for($this->project)->create();

    Livewire::test(ListSubcontracts::class)
        ->assertCanSeeTableRecords([$subcontract])
        ->assertCanNotSeeTableRecords([$owner]);
});

test('an executed subcontract has a read-only overview page', function () {
    $contract = Contract::factory()->subcontract()->for($this->project)->create([
        'status' => ContractStatus::Executed,
    ]);

    Livewire::test(ViewSubcontract::class, ['record' => $contract->getKey()])
        ->assertOk()
        ->assertSee('Contract overview')
        ->assertSee('Schedule of Values')
        ->assertSee('Financial terms are locked');
});
