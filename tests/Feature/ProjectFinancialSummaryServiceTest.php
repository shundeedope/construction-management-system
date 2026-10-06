<?php

use App\Enums\ChangeOrderType;
use App\Enums\InvoiceStatus;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderLineItem;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Services\ProjectFinancialSummaryService;

test('only approved owner and subcontract changes revise their respective project totals', function () {
    $project = Project::factory()->create();
    $ownerContract = Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id, 'original_amount' => 500_000_00]);
    $subcontract = Contract::factory()->subcontract()->for($project)->executed()->create(['original_amount' => 185_000_00]);

    $approvedOwnerChange = ChangeOrder::factory()->for($project)->for($ownerContract)->approved()->create(['type' => ChangeOrderType::Owner]);
    $draftOwnerChange = ChangeOrder::factory()->for($project)->for($ownerContract)->create(['type' => ChangeOrderType::Owner]);
    $submittedOwnerChange = ChangeOrder::factory()->for($project)->for($ownerContract)->submitted()->create(['type' => ChangeOrderType::Owner]);
    $rejectedOwnerChange = ChangeOrder::factory()->for($project)->for($ownerContract)->rejected()->create(['type' => ChangeOrderType::Owner]);
    $approvedSubcontractChange = ChangeOrder::factory()->subcontract()->for($project)->for($subcontract)->approved()->create();

    ChangeOrderLineItem::factory()->for($approvedOwnerChange)->create(['amount' => 15_000_00]);
    ChangeOrderLineItem::factory()->for($draftOwnerChange)->create(['amount' => 9_999_99]);
    ChangeOrderLineItem::factory()->for($submittedOwnerChange)->create(['amount' => 8_888_88]);
    ChangeOrderLineItem::factory()->for($rejectedOwnerChange)->create(['amount' => 7_777_77]);
    ChangeOrderLineItem::factory()->for($approvedSubcontractChange)->create(['amount' => 10_000_00]);

    $summary = app(ProjectFinancialSummaryService::class)->summary($project);

    expect($summary['approved_owner_changes']->cents)->toBe(15_000_00)
        ->and($summary['revised_owner_value']->cents)->toBe(515_000_00)
        ->and($summary['approved_subcontract_changes']->cents)->toBe(10_000_00)
        ->and($summary['revised_commitments']->cents)->toBe(195_000_00)
        ->and($summary['gross_margin']->cents)->toBe(320_000_00)
        ->and($summary['gross_margin_percentage'])->toBe(62.14);
});

test('a zero revised owner value has a zero margin percentage', function () {
    $project = Project::factory()->create();
    Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id, 'original_amount' => 0]);
    Contract::factory()->subcontract()->for($project)->executed()->create(['original_amount' => 10_000_00]);

    $summary = app(ProjectFinancialSummaryService::class)->summary($project);

    expect($summary['revised_owner_value']->cents)->toBe(0)
        ->and($summary['gross_margin']->cents)->toBe(-10_000_00)
        ->and($summary['gross_margin_percentage'])->toBe(0.0);
});

test('only finalized non-void client invoices update billed and remaining project value', function () {
    $project = Project::factory()->create();
    $contract = Contract::factory()->for($project)->executed()->create([
        'client_id' => $project->client_id,
        'original_amount' => 1_000_00,
    ]);

    Invoice::factory()->for($project)->for($contract)->create([
        'status' => InvoiceStatus::Draft,
        'subtotal' => 100_00,
    ]);
    Invoice::factory()->for($project)->for($contract)->create([
        'status' => InvoiceStatus::Approved,
        'subtotal' => 200_00,
    ]);
    Invoice::factory()->for($project)->for($contract)->create([
        'status' => InvoiceStatus::Sent,
        'subtotal' => 300_00,
    ]);
    Invoice::factory()->for($project)->for($contract)->create([
        'status' => InvoiceStatus::Void,
        'subtotal' => 400_00,
    ]);

    $summary = app(ProjectFinancialSummaryService::class)->summary($project);

    expect($summary['client_invoiced']->cents)->toBe(500_00)
        ->and($summary['remaining_to_bill']->cents)->toBe(500_00);
});

test('only approved subcontractor invoices update invoiced cost and remaining commitment', function () {
    $project = Project::factory()->create();
    $contract = Contract::factory()->subcontract()->for($project)->executed()->create(['original_amount' => 1_000_00]);

    Invoice::factory()->subcontractor()->for($project)->for($contract)->create(['status' => InvoiceStatus::Draft, 'subtotal' => 100_00]);
    Invoice::factory()->subcontractor()->for($project)->for($contract)->create(['status' => InvoiceStatus::Approved, 'subtotal' => 200_00]);
    Invoice::factory()->subcontractor()->for($project)->for($contract)->create(['status' => InvoiceStatus::Void, 'subtotal' => 300_00]);

    $summary = app(ProjectFinancialSummaryService::class)->summary($project);

    expect($summary['subcontractor_invoiced']->cents)->toBe(200_00)
        ->and($summary['remaining_commitment']->cents)->toBe(800_00);
});

test('it includes received, paid, receivable, and payable figures after partial and full payments', function () {
    $project = Project::factory()->create();
    $clientContract = Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id, 'original_amount' => 1_000_00]);
    $subcontract = Contract::factory()->subcontract()->for($project)->executed()->create(['original_amount' => 800_00]);
    $clientInvoice = Invoice::factory()->for($project)->for($clientContract)->approved()->create(['project_id' => $project->id, 'subtotal' => 1_000_00, 'total' => 900_00]);
    $subcontractorInvoice = Invoice::factory()->subcontractor()->for($project)->for($subcontract)->approved()->create(['project_id' => $project->id, 'subtotal' => 800_00, 'total' => 700_00]);

    Payment::factory()->for($project)->for($clientInvoice)->create(['project_id' => $project->id, 'direction' => 'incoming', 'amount' => 400_00]);
    Payment::factory()->for($project)->for($subcontractorInvoice)->outgoing()->create(['project_id' => $project->id, 'amount' => 700_00]);

    $summary = app(ProjectFinancialSummaryService::class)->summary($project);

    expect($summary['received']->cents)->toBe(400_00)
        ->and($summary['paid']->cents)->toBe(700_00)
        ->and($summary['receivable']->cents)->toBe(500_00)
        ->and($summary['payable']->cents)->toBe(0)
        ->and($summary['remaining_to_bill']->cents)->toBe(0)
        ->and($summary['remaining_commitment']->cents)->toBe(0);
});
