<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\ClientInvoices\Pages\CreateClientInvoice;
use App\Filament\Resources\ClientInvoices\Pages\EditClientInvoice;
use App\Filament\Resources\ClientInvoices\Pages\ListClientInvoices;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
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
    $this->contract = Contract::factory()->for($this->project)->executed()->create([
        'client_id' => $this->project->client_id,
        'original_amount' => 1_000_00,
        'retention_basis_points' => 500,
    ]);
    $this->line = ContractLineItem::factory()->for($this->contract)->create(['amount' => 1_000_00]);
    $this->actingAs($this->manager);
});

test('an authorized manager can create a calculated client invoice from an owner contract', function () {
    Livewire::test(CreateClientInvoice::class)
        ->fillForm([
            'number' => 'INV-UI-1001',
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->endOfMonth()->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'lineItems' => [[
                'contract_line_item_id' => $this->line->id,
                'scheduled_value' => '1000.00',
                'previously_billed' => '0.00',
                'work_completed_this_period' => '100.01',
                'materials_stored' => '0.00',
                'retention_amount' => '0.00',
                'current_due' => '0.00',
                'sort_order' => 0,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $invoice = Invoice::query()->where('number', 'INV-UI-1001')->firstOrFail();

    expect($invoice->created_by)->toBe($this->manager->id)
        ->and($invoice->subtotal)->toBe(100_01)
        ->and($invoice->retention_amount)->toBe(500)
        ->and($invoice->total)->toBe(95_01);
});

test('the client-invoice list excludes invoices outside the users financial project scope', function () {
    $visible = Invoice::factory()->for($this->project)->for($this->contract)->create();
    $otherProject = Project::factory()->create();
    $hidden = Invoice::factory()->for($otherProject)->for(Contract::factory()->for($otherProject)->create(['client_id' => $otherProject->client_id]))->create();

    Livewire::test(ListClientInvoices::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
});

test('accounting can approve a client invoice through the resource and finalized values are locked', function () {
    $invoice = Invoice::factory()->for($this->project)->for($this->contract)->create([
        'project_id' => $this->project->id,
        'contract_id' => $this->contract->id,
        'subtotal' => 0,
        'retention_amount' => 0,
        'total' => 0,
    ]);
    InvoiceLineItem::factory()->for($invoice)->for($this->line)->create(['work_completed_this_period' => 100_00]);
    $accounting = userWithRole(Role::Accounting);
    $this->actingAs($accounting);

    Livewire::test(EditClientInvoice::class, ['record' => $invoice->id])
        ->callAction(TestAction::make('approve'))
        ->assertNotified();

    expect($invoice->fresh()->status->value)->toBe('approved');
    expect($accounting->can('update', $invoice->fresh()))->toBeFalse();
});

test('an approved client invoice stays reachable and can be marked as sent from the list', function () {
    $invoice = Invoice::factory()->for($this->project)->for($this->contract)->approved()->create();
    $accounting = userWithRole(Role::Accounting);
    $this->actingAs($accounting);

    Livewire::test(EditClientInvoice::class, ['record' => $invoice->id])
        ->assertOk();

    Livewire::test(ListClientInvoices::class)
        ->callAction(TestAction::make('send')->table($invoice))
        ->assertNotified();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

test('the overdue tab lists only unpaid invoices past their due date', function () {
    $overdue = Invoice::factory()->for($this->project)->for($this->contract)->approved()->create(['due_date' => now()->subWeek()]);
    $current = Invoice::factory()->for($this->project)->for($this->contract)->approved()->create(['due_date' => now()->addWeek()]);
    $draft = Invoice::factory()->for($this->project)->for($this->contract)->create(['due_date' => now()->subWeek()]);

    Livewire::test(ListClientInvoices::class)
        ->set('activeTab', 'overdue')
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$current, $draft]);
});
