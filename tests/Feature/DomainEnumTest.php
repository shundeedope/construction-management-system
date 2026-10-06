<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Enums\CompanySettingType;
use App\Enums\ComplianceStatus;
use App\Enums\ComplianceSubject;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\DocumentCategory;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\LeaveTransactionType;
use App\Enums\NumberSequenceReset;
use App\Enums\PaymentDirection;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TimeEntryStatus;
use App\Enums\VendorType;
use App\Models\ChangeOrder;
use App\Models\ComplianceRecord;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\LeaveTransaction;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Vendor;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\DB;

/**
 * The initial values documented in the Phase 1 migration comments. If a migration comment and
 * its enum ever drift apart, this dataset is the thing that fails.
 */
dataset('documented enums', [
    'project status' => [ProjectStatus::class, ['planning', 'active', 'on_hold', 'completed', 'cancelled']],
    'contract type' => [ContractType::class, ['owner', 'subcontract', 'purchase_order']],
    'contract status' => [ContractStatus::class, ['draft', 'pending_approval', 'executed', 'closed', 'cancelled']],
    'change order type' => [ChangeOrderType::class, ['owner', 'subcontract']],
    'change order status' => [ChangeOrderStatus::class, ['draft', 'submitted', 'approved', 'rejected', 'cancelled']],
    'invoice type' => [InvoiceType::class, ['client', 'subcontractor']],
    'invoice status' => [InvoiceStatus::class, ['draft', 'submitted', 'approved', 'sent', 'partially_paid', 'paid', 'rejected', 'void']],
    'payment direction' => [PaymentDirection::class, ['incoming', 'outgoing']],
    'document category' => [DocumentCategory::class, ['contract', 'change_order', 'client_invoice', 'subcontractor_invoice', 'plan', 'compliance', 'photo', 'general']],
    'time entry status' => [TimeEntryStatus::class, ['draft', 'submitted', 'approved', 'rejected']],
    'leave transaction type' => [LeaveTransactionType::class, ['accrual', 'usage', 'adjustment']],
    'compliance subject' => [ComplianceSubject::class, ['employee', 'vendor', 'project']],
    'compliance status' => [ComplianceStatus::class, ['valid', 'expiring_soon', 'expired', 'missing']],
    'vendor type' => [VendorType::class, ['subcontractor', 'supplier']],
    'project role' => [ProjectRole::class, ['project_manager', 'site_supervisor', 'employee', 'other']],
    'number sequence reset' => [NumberSequenceReset::class, ['never', 'yearly']],
    'company setting type' => [CompanySettingType::class, ['string', 'integer', 'boolean', 'json', 'encrypted']],
]);

it('covers every documented initial value exactly', function (string $enum, array $values) {
    $cases = array_map(fn ($case): string => $case->value, $enum::cases());

    expect($cases)->toEqualCanonicalizing($values);

    foreach ($values as $value) {
        expect($enum::from($value)->value)->toBe($value);
    }
})->with('documented enums');

it('gives every UI-facing workflow enum a label and a color', function () {
    $uiFacing = [
        ProjectStatus::class, ContractType::class, ContractStatus::class,
        ChangeOrderType::class, ChangeOrderStatus::class, InvoiceType::class,
        InvoiceStatus::class, PaymentDirection::class, DocumentCategory::class,
        TimeEntryStatus::class, LeaveTransactionType::class, ComplianceStatus::class,
    ];

    foreach ($uiFacing as $enum) {
        expect(is_a($enum, HasLabel::class, allow_string: true))->toBeTrue("{$enum} must implement HasLabel")
            ->and(is_a($enum, HasColor::class, allow_string: true))->toBeTrue("{$enum} must implement HasColor");

        foreach ($enum::cases() as $case) {
            expect($case->getLabel())->toBeString()->not->toBeEmpty()
                ->and($case->getColor())->toBeString()->not->toBeEmpty();
        }
    }
});

it('round-trips persisted string columns through their enum casts', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    $contract = Contract::factory()->for($project)->create([
        'type' => ContractType::Owner,
        'status' => ContractStatus::Executed,
    ]);
    $changeOrder = ChangeOrder::factory()->for($project)->for($contract)->create([
        'type' => ChangeOrderType::Owner,
        'status' => ChangeOrderStatus::Approved,
    ]);
    $invoice = Invoice::factory()->for($project)->for($contract)->create([
        'type' => InvoiceType::Client,
        'status' => InvoiceStatus::Sent,
    ]);
    $payment = Payment::factory()->for($project)->for($invoice)->create(['direction' => PaymentDirection::Incoming]);
    $document = Document::factory()->for($project)->create(['category' => DocumentCategory::Plan]);
    $timeEntry = TimeEntry::factory()->for($project)->create(['status' => TimeEntryStatus::Submitted]);
    $leave = LeaveTransaction::factory()->create(['type' => LeaveTransactionType::Accrual]);
    $vendor = Vendor::factory()->create(['vendor_type' => VendorType::Supplier]);
    $compliance = ComplianceRecord::factory()->create(['status' => ComplianceStatus::ExpiringSoon]);

    expect($project->fresh()->status)->toBe(ProjectStatus::Active)
        ->and($contract->fresh()->type)->toBe(ContractType::Owner)
        ->and($contract->fresh()->status)->toBe(ContractStatus::Executed)
        ->and($changeOrder->fresh()->type)->toBe(ChangeOrderType::Owner)
        ->and($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::Approved)
        ->and($invoice->fresh()->type)->toBe(InvoiceType::Client)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($payment->fresh()->direction)->toBe(PaymentDirection::Incoming)
        ->and($document->fresh()->category)->toBe(DocumentCategory::Plan)
        ->and($timeEntry->fresh()->status)->toBe(TimeEntryStatus::Submitted)
        ->and($leave->fresh()->type)->toBe(LeaveTransactionType::Accrual)
        ->and($vendor->fresh()->vendor_type)->toBe(VendorType::Supplier)
        ->and($compliance->fresh()->status)->toBe(ComplianceStatus::ExpiringSoon);
});

it('keeps the underlying database columns as plain strings', function () {
    $project = Project::factory()->create(['status' => ProjectStatus::OnHold]);

    $raw = DB::table('projects')->where('id', $project->id)->value('status');

    expect($raw)->toBe('on_hold')->toBeString();
});

it('fails loudly when an unknown value is read back from the database', function () {
    $project = Project::factory()->create();

    DB::table('projects')->where('id', $project->id)->update(['status' => 'not_a_status']);

    expect(fn () => Project::query()->find($project->id)->status)->toThrow(ValueError::class);
});

it('models workflow transitions on the status enums', function () {
    expect(ContractStatus::Draft->canTransitionTo(ContractStatus::PendingApproval))->toBeTrue()
        ->and(ContractStatus::Draft->canTransitionTo(ContractStatus::Executed))->toBeFalse()
        ->and(ContractStatus::Closed->allowedTransitions())->toBe([])
        ->and(ContractStatus::Executed->isFinancesLocked())->toBeTrue()
        ->and(ContractStatus::Draft->isFinancesLocked())->toBeFalse();

    expect(ChangeOrderStatus::Submitted->canTransitionTo(ChangeOrderStatus::Approved))->toBeTrue()
        ->and(ChangeOrderStatus::Approved->canTransitionTo(ChangeOrderStatus::Draft))->toBeFalse()
        ->and(ChangeOrderStatus::Approved->countsTowardsProjectFinancials())->toBeTrue()
        ->and(ChangeOrderStatus::Submitted->countsTowardsProjectFinancials())->toBeFalse();

    expect(InvoiceStatus::Void->countsTowardsProjectFinancials())->toBeFalse()
        ->and(InvoiceStatus::Approved->countsTowardsProjectFinancials())->toBeTrue()
        ->and(InvoiceStatus::Draft->countsTowardsProjectFinancials())->toBeFalse()
        ->and(InvoiceStatus::Sent->acceptsPayments())->toBeTrue()
        ->and(InvoiceStatus::Draft->acceptsPayments())->toBeFalse();

    expect(TimeEntryStatus::Approved->isLocked())->toBeTrue()
        ->and(TimeEntryStatus::Approved->allowedTransitions())->toBe([]);
});
