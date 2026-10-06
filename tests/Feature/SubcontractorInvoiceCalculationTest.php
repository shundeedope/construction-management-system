<?php

use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Services\SubcontractorInvoiceCalculationService;
use Illuminate\Validation\ValidationException;

test('it calculates subcontractor progress billing and prevents billing beyond the revised commitment', function () {
    $project = Project::factory()->create();
    $contract = Contract::factory()->subcontract()->for($project)->executed()->create([
        'original_amount' => 200_00,
        'retention_basis_points' => 500,
    ]);
    $line = ContractLineItem::factory()->for($contract)->create(['amount' => 200_00]);
    $invoice = subcontractorInvoice($project, $contract, 'VINV-1001');
    $invoiceLine = InvoiceLineItem::factory()->for($invoice)->for($line)->create(['work_completed_this_period' => 100_01]);

    $totals = app(SubcontractorInvoiceCalculationService::class)->sync($invoice);

    expect($invoiceLine->fresh()->scheduled_value)->toBe(200_00)
        ->and($invoiceLine->fresh()->retention_amount)->toBe(500)
        ->and($invoiceLine->fresh()->current_due)->toBe(95_01)
        ->and($totals['total']->cents)->toBe(95_01);

    $invoice->update(['status' => InvoiceStatus::Approved]);

    $overbilled = subcontractorInvoice($project, $contract, 'VINV-1002');
    InvoiceLineItem::factory()->for($overbilled)->for($line)->create(['work_completed_this_period' => 100_01]);

    expect(fn () => app(SubcontractorInvoiceCalculationService::class)->sync($overbilled))
        ->toThrow(ValidationException::class);
});

test('it blocks owner contracts, cross-project contracts, and contract lines that do not belong to the commitment', function () {
    $project = Project::factory()->create();
    $ownerContract = Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id]);
    $ownerInvoice = subcontractorInvoice($project, $ownerContract, 'VINV-2001');
    InvoiceLineItem::factory()->for($ownerInvoice)->for(ContractLineItem::factory()->for($ownerContract))->create();

    expect(fn () => app(SubcontractorInvoiceCalculationService::class)->sync($ownerInvoice))
        ->toThrow(ValidationException::class);

    $otherProject = Project::factory()->create();
    $otherContract = Contract::factory()->subcontract()->for($otherProject)->executed()->create();
    $mismatched = subcontractorInvoice($project, $otherContract, 'VINV-2002');
    InvoiceLineItem::factory()->for($mismatched)->for(ContractLineItem::factory()->for($otherContract))->create();

    expect(fn () => app(SubcontractorInvoiceCalculationService::class)->sync($mismatched))
        ->toThrow(ValidationException::class);
});
