<?php

use App\Enums\ChangeOrderType;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Enums\TimeEntryStatus;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\ChangeOrderWorkflowService;
use App\Services\ClientInvoiceWorkflowService;
use App\Services\ContractWorkflowService;
use App\Services\PaymentService;
use App\Services\ProjectFinancialSummaryService;
use App\Services\SubcontractorInvoiceWorkflowService;
use App\Services\TimeEntryApprovalService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->active()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true, canManageDocuments: true);
    $this->accounting = userWithRole(Role::Accounting);
    $this->employeeUser = assignToProject(userWithRole(Role::Employee), $this->project);
    $this->employee = Employee::factory()->for($this->employeeUser)->create();
});

test('the internal construction workflow preserves financial, activity, document, and time-entry state', function () {
    $contractWorkflow = app(ContractWorkflowService::class);
    $changeWorkflow = app(ChangeOrderWorkflowService::class);

    $ownerContract = Contract::factory()->for($this->project)->create([
        'client_id' => $this->project->client_id,
        'created_by' => $this->manager->id,
        'original_amount' => 0,
    ]);
    $ownerLine = ContractLineItem::factory()->for($ownerContract)->create(['amount' => 100_000_00]);
    $ownerContract = $contractWorkflow->execute($contractWorkflow->submitForApproval($ownerContract, $this->manager), $this->manager);

    $subcontract = Contract::factory()->subcontract()->for($this->project)->create([
        'created_by' => $this->manager->id,
        'original_amount' => 0,
    ]);
    $subcontractLine = ContractLineItem::factory()->for($subcontract)->create(['amount' => 50_000_00]);
    $subcontract = $contractWorkflow->execute($contractWorkflow->submitForApproval($subcontract, $this->manager), $this->manager);

    $secondSubcontract = Contract::factory()->subcontract()->for($this->project)->create([
        'created_by' => $this->manager->id,
        'original_amount' => 0,
    ]);
    ContractLineItem::factory()->for($secondSubcontract)->create(['amount' => 30_000_00]);
    $secondSubcontract = $contractWorkflow->execute($contractWorkflow->submitForApproval($secondSubcontract, $this->manager), $this->manager);

    $ownerChange = ChangeOrder::factory()->for($this->project)->for($ownerContract)->create([
        'type' => ChangeOrderType::Owner,
        'created_by' => $this->manager->id,
    ]);
    $ownerChange->lineItems()->create(['description' => 'Client-requested finish upgrade', 'amount' => 10_000_00]);
    $ownerChange = $changeWorkflow->approve($changeWorkflow->submit($ownerChange, $this->manager), $this->manager);

    $subcontractChange = ChangeOrder::factory()->subcontract()->for($this->project)->for($subcontract)->create([
        'created_by' => $this->manager->id,
    ]);
    $subcontractChange->lineItems()->create(['description' => 'Material upgrade', 'amount' => 5_000_00]);
    $subcontractChange = $changeWorkflow->approve($changeWorkflow->submit($subcontractChange, $this->manager), $this->manager);

    $clientInvoice = Invoice::factory()->for($this->project)->for($ownerContract)->create([
        'project_id' => $this->project->id,
        'contract_id' => $ownerContract->id,
        'created_by' => $this->accounting->id,
        'subtotal' => 0,
        'total' => 0,
    ]);
    InvoiceLineItem::factory()->for($clientInvoice)->for($ownerLine)->create([
        'scheduled_value' => 100_000_00,
        'work_completed_this_period' => 50_000_00,
        'current_due' => 47_500_00,
        'retention_amount' => 2_500_00,
    ]);
    $clientInvoiceWorkflow = app(ClientInvoiceWorkflowService::class);
    $clientInvoice = $clientInvoiceWorkflow->send($clientInvoiceWorkflow->approve($clientInvoice, $this->accounting), $this->accounting);
    app(PaymentService::class)->record($clientInvoice, $this->accounting, [
        'amount' => 20_000_00,
        'payment_date' => today()->toDateString(),
        'method' => 'ach',
        'reference' => 'E2E-CLIENT-001',
    ]);

    $vendorInvoice = subcontractorInvoice($this->project, $subcontract, 'E2E-VENDOR-001');
    InvoiceLineItem::factory()->for($vendorInvoice)->for($subcontractLine)->create([
        'scheduled_value' => 50_000_00,
        'work_completed_this_period' => 25_000_00,
        'current_due' => 25_000_00,
    ]);
    $vendorInvoiceWorkflow = app(SubcontractorInvoiceWorkflowService::class);
    $vendorInvoice = $vendorInvoiceWorkflow->approve($vendorInvoiceWorkflow->submit($vendorInvoice, $this->manager), $this->accounting);
    app(PaymentService::class)->record($vendorInvoice, $this->accounting, [
        'amount' => 10_000_00,
        'payment_date' => today()->toDateString(),
        'method' => 'check',
        'reference' => 'E2E-VENDOR-001',
    ]);

    $document = Document::factory()->for($this->project)->create(['documentable_type' => Contract::class, 'documentable_id' => $ownerContract->id]);
    $timeEntryService = app(TimeEntryApprovalService::class);
    $timeEntry = $timeEntryService->create($this->employee, [
        'project_id' => $this->project->id,
        'work_date' => today()->toDateString(),
        'regular_hours' => 8,
        'overtime_hours' => 0,
        'is_billable' => true,
        'description' => 'Riverside walkthrough',
    ], $this->employeeUser);
    $timeEntry = $timeEntryService->approve($timeEntryService->submit($timeEntry, $this->employeeUser), $this->manager);

    $summary = app(ProjectFinancialSummaryService::class)->summary($this->project);

    expect($ownerContract->original_amount)->toBe(100_000_00)
        ->and($subcontract->original_amount)->toBe(50_000_00)
        ->and($secondSubcontract->original_amount)->toBe(30_000_00)
        ->and($summary['revised_owner_value']->cents)->toBe(110_000_00)
        ->and($summary['revised_commitments']->cents)->toBe(85_000_00)
        ->and($summary['gross_margin']->cents)->toBe(25_000_00)
        ->and($summary['received']->cents)->toBe(20_000_00)
        ->and($summary['paid']->cents)->toBe(10_000_00)
        ->and($document->documentable->is($ownerContract))->toBeTrue()
        ->and($timeEntry->status)->toBe(TimeEntryStatus::Approved)
        ->and(app(ActivityLogger::class)->queryFor($ownerChange)->where('event', 'approved')->exists())->toBeTrue()
        ->and(app(ActivityLogger::class)->queryFor($subcontractChange)->where('event', 'approved')->exists())->toBeTrue()
        ->and(app(ActivityLogger::class)->queryFor($clientInvoice)->where('event', 'sent')->exists())->toBeTrue()
        ->and(app(ActivityLogger::class)->queryFor($vendorInvoice)->where('event', 'approved')->exists())->toBeTrue();
});
