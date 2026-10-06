<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\SubcontractorInvoices\Pages\CreateSubcontractorInvoice;
use App\Filament\Resources\SubcontractorInvoices\Pages\EditSubcontractorInvoice;
use App\Filament\Resources\SubcontractorInvoices\Pages\ListSubcontractorInvoices;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Services\SubcontractorInvoiceCalculationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->project = Project::factory()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->contract = Contract::factory()->subcontract()->for($this->project)->executed()->create(['original_amount' => 1_000_00]);
    $this->line = ContractLineItem::factory()->for($this->contract)->create(['amount' => 1_000_00]);
    $this->actingAs($this->manager);
});

test('an authorized manager can create and submit a subcontractor invoice', function () {
    Livewire::test(CreateSubcontractorInvoice::class)
        ->fillForm([
            'number' => 'VINV-UI-1001',
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

    $invoice = Invoice::query()->where('number', 'VINV-UI-1001')->firstOrFail();

    Livewire::test(EditSubcontractorInvoice::class, ['record' => $invoice->id])
        ->callAction(TestAction::make('submit'))
        ->assertNotified();

    expect($invoice->fresh()->status->value)->toBe('submitted')
        ->and($invoice->subtotal)->toBe(100_01);
});

test('accounting can approve a submitted subcontractor invoice and other project invoices remain hidden', function () {
    $invoice = subcontractorInvoice($this->project, $this->contract, 'VINV-UI-2001');
    InvoiceLineItem::factory()->for($invoice)->for($this->line)->create(['work_completed_this_period' => 100_00]);
    app(SubcontractorInvoiceCalculationService::class)->sync($invoice);
    $invoice->update(['status' => 'submitted']);

    $otherProject = Project::factory()->create();
    $hidden = Invoice::factory()->subcontractor()->for($otherProject)->for(Contract::factory()->subcontract()->for($otherProject)->executed())->create();
    $accounting = userWithRole(Role::Accounting);
    $this->actingAs($accounting);

    expect($accounting->can('approve', $invoice->fresh()))->toBeTrue();
    expect($accounting->can('view', $invoice->fresh()))->toBeTrue();

    Livewire::test(EditSubcontractorInvoice::class, ['record' => $invoice->id])
        ->assertOk()
        ->callAction('approve')
        ->assertNotified();

    expect($invoice->fresh()->status->value)->toBe('approved');

    $this->actingAs($this->manager);
    Livewire::test(ListSubcontractorInvoices::class)
        ->assertCanSeeTableRecords([$invoice])
        ->assertCanNotSeeTableRecords([$hidden]);
});
