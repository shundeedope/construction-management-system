<?php

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Services\ClientInvoiceWorkflowService;
use App\Services\InvoiceCalculationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function clientInvoice(Project $project, Contract $contract, string $number): Invoice
{
    return Invoice::factory()->for($project)->for($contract)->create([
        'number' => $number,
        'project_id' => $project->id,
        'contract_id' => $contract->id,
        'status' => InvoiceStatus::Draft,
        'subtotal' => 0,
        'retention_amount' => 0,
        'total' => 0,
    ]);
}

test('it calculates prior billing, current due, and exact retention across billing periods', function () {
    $project = Project::factory()->create();
    $contract = Contract::factory()->for($project)->executed()->create([
        'client_id' => $project->client_id,
        'original_amount' => 1_000_00,
        'retention_basis_points' => 500,
    ]);
    $contractLine = ContractLineItem::factory()->for($contract)->create(['amount' => 1_000_00]);
    $actor = userWithRole(Role::Administrator);

    $first = clientInvoice($project, $contract, 'INV-1001');
    InvoiceLineItem::factory()->for($first)->for($contractLine)->create([
        'work_completed_this_period' => 100_01,
        'materials_stored' => 0,
    ]);

    app(InvoiceCalculationService::class)->sync($first);
    app(ClientInvoiceWorkflowService::class)->approve($first, $actor);

    $second = clientInvoice($project, $contract, 'INV-1002');
    $secondLine = InvoiceLineItem::factory()->for($second)->for($contractLine)->create([
        'work_completed_this_period' => 200_00,
        'materials_stored' => 10_00,
    ]);

    $totals = app(InvoiceCalculationService::class)->sync($second);

    expect($first->fresh()->subtotal)->toBe(100_01)
        ->and($first->fresh()->retention_amount)->toBe(500)
        ->and($first->fresh()->total)->toBe(95_01)
        ->and($secondLine->fresh()->scheduled_value)->toBe(1_000_00)
        ->and($secondLine->fresh()->previously_billed)->toBe(100_01)
        ->and($secondLine->fresh()->retention_amount)->toBe(1_050)
        ->and($secondLine->fresh()->current_due)->toBe(199_50)
        ->and($totals['subtotal']->cents)->toBe(210_00)
        ->and($totals['retention']->cents)->toBe(1_050)
        ->and($totals['total']->cents)->toBe(199_50);
});

test('it blocks mismatched contracts, line overbilling, and aggregate contract overbilling', function () {
    $project = Project::factory()->create();
    $contract = Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id, 'original_amount' => 100_00]);
    $line = ContractLineItem::factory()->for($contract)->create(['amount' => 100_00]);
    $invoice = clientInvoice($project, $contract, 'INV-2001');
    InvoiceLineItem::factory()->for($invoice)->for($line)->create(['work_completed_this_period' => 100_01]);

    expect(fn () => app(InvoiceCalculationService::class)->sync($invoice))
        ->toThrow(ValidationException::class);

    $otherProject = Project::factory()->create();
    $otherContract = Contract::factory()->for($otherProject)->executed()->create(['client_id' => $otherProject->client_id]);
    $mismatched = clientInvoice($project, $contract, 'INV-2002');
    $otherLine = ContractLineItem::factory()->for($otherContract)->create();
    InvoiceLineItem::factory()->for($mismatched)->for($otherLine)->create(['work_completed_this_period' => 1]);

    expect(fn () => app(InvoiceCalculationService::class)->sync($mismatched))
        ->toThrow(ValidationException::class);

    $aggregateContract = Contract::factory()->for($project)->executed()->create([
        'client_id' => $project->client_id,
        'original_amount' => 100_00,
    ]);
    $aggregateLine = ContractLineItem::factory()->for($aggregateContract)->create(['amount' => 1_000_00]);
    $aggregateInvoice = clientInvoice($project, $aggregateContract, 'INV-2003');
    InvoiceLineItem::factory()->for($aggregateInvoice)->for($aggregateLine)->create(['work_completed_this_period' => 100_01]);

    expect(fn () => app(InvoiceCalculationService::class)->sync($aggregateInvoice))
        ->toThrow(ValidationException::class);
});

test('it blocks negative billing and duplicate contract lines on the same invoice', function () {
    $project = Project::factory()->create();
    $contract = Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id, 'original_amount' => 500_00]);
    $line = ContractLineItem::factory()->for($contract)->create(['amount' => 500_00]);

    $negative = clientInvoice($project, $contract, 'INV-2004');
    InvoiceLineItem::factory()->for($negative)->for($line)->create(['work_completed_this_period' => -1]);

    expect(fn () => app(InvoiceCalculationService::class)->sync($negative))
        ->toThrow(ValidationException::class);

    $duplicate = clientInvoice($project, $contract, 'INV-2005');
    InvoiceLineItem::factory()->for($duplicate)->for($line)->count(2)->create(['work_completed_this_period' => 1]);

    expect(fn () => app(InvoiceCalculationService::class)->sync($duplicate))
        ->toThrow(ValidationException::class);
});
