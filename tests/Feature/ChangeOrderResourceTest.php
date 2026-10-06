<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\ChangeOrders\Pages\CreateChangeOrder;
use App\Filament\Resources\ChangeOrders\Pages\EditChangeOrder;
use App\Filament\Resources\ChangeOrders\Pages\ListChangeOrders;
use App\Models\ChangeOrder;
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
    $this->contract = Contract::factory()->for($this->project)->executed()->create(['client_id' => $this->project->client_id]);
    $this->actingAs($this->manager);
});

test('an authorized manager can create an owner change order with exact line totals', function () {
    Livewire::test(CreateChangeOrder::class)
        ->fillForm([
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'type' => ChangeOrderType::Owner->value,
            'number' => 'OCO-1001',
            'title' => 'Additional site work',
            'lineItems' => [[
                'description' => 'Additional supervision',
                'amount' => '1000.50',
                'sort_order' => 0,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $changeOrder = ChangeOrder::query()->where('number', 'OCO-1001')->firstOrFail();

    expect($changeOrder->created_by)->toBe($this->manager->id)
        ->and($changeOrder->lineItems()->sum('amount'))->toBe(100_050);
});

test('a manager can submit and approve a draft through the resource actions', function () {
    $changeOrder = ChangeOrder::factory()->for($this->project)->for($this->contract)->create();
    $changeOrder->lineItems()->create(['description' => 'Labor escalation', 'amount' => 25_000, 'sort_order' => 0]);

    Livewire::test(EditChangeOrder::class, ['record' => $changeOrder->id])
        ->assertOk()
        ->callAction(TestAction::make('submit'))
        ->assertNotified();

    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::Submitted);

    Livewire::test(EditChangeOrder::class, ['record' => $changeOrder->id])
        ->assertOk()
        ->callAction(TestAction::make('approve'))
        ->assertNotified();

    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::Approved);
});

test('the change-order list excludes records outside the users financial project scope', function () {
    $visible = ChangeOrder::factory()->for($this->project)->for($this->contract)->create();
    $otherProject = Project::factory()->create();
    $hidden = ChangeOrder::factory()->for($otherProject)->for(Contract::factory()->for($otherProject)->create(['client_id' => $otherProject->client_id]))->create();

    Livewire::test(ListChangeOrders::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
});

test('a crafted Livewire save cannot rewrite a submitted change order', function () {
    $changeOrder = ChangeOrder::factory()->for($this->project)->for($this->contract)->submitted()->create(['title' => 'Original title']);

    Livewire::test(EditChangeOrder::class, ['record' => $changeOrder->id])
        ->set('data.title', 'Tampered title')
        ->call('save')
        ->assertForbidden();

    expect($changeOrder->fresh()->title)->toBe('Original title');
});

test('a submitted change order can be approved straight from the list', function () {
    $changeOrder = ChangeOrder::factory()->for($this->project)->for($this->contract)->submitted()->create();

    Livewire::test(ListChangeOrders::class)
        ->callAction(TestAction::make('approve')->table($changeOrder))
        ->assertNotified();

    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::Approved);
});

test('a decided change order opens read-only instead of returning a 403', function () {
    $changeOrder = ChangeOrder::factory()->for($this->project)->for($this->contract)->approved()->create();

    Livewire::test(EditChangeOrder::class, ['record' => $changeOrder->id])
        ->assertOk();

    expect($this->manager->can('update', $changeOrder))->toBeFalse();
});

test('the awaiting-approval tab lists only submitted change orders', function () {
    $submitted = ChangeOrder::factory()->for($this->project)->for($this->contract)->submitted()->create();
    $draft = ChangeOrder::factory()->for($this->project)->for($this->contract)->create();

    Livewire::test(ListChangeOrders::class)
        ->set('activeTab', 'awaiting_approval')
        ->assertCanSeeTableRecords([$submitted])
        ->assertCanNotSeeTableRecords([$draft]);
});
