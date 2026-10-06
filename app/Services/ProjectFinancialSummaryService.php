<?php

namespace App\Services;

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\ChangeOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Support\Money;

class ProjectFinancialSummaryService
{
    public function __construct(private ProjectContractSummaryService $contractSummaryService) {}

    /**
     * @return array{original_owner_value: Money, original_commitments: Money, approved_owner_changes: Money, approved_subcontract_changes: Money, revised_owner_value: Money, revised_commitments: Money, gross_margin: Money, gross_margin_percentage: float, client_invoiced: Money, remaining_to_bill: Money, subcontractor_invoiced: Money, remaining_commitment: Money, received: Money, paid: Money, receivable: Money, payable: Money}
     */
    public function summary(Project $project): array
    {
        $contractSummary = $this->contractSummaryService->summary($project);
        $approvedChanges = $this->approvedChanges($project);
        $approvedOwnerChanges = $approvedChanges[ChangeOrderType::Owner->value];
        $approvedSubcontractChanges = $approvedChanges[ChangeOrderType::Subcontract->value];
        $revisedOwnerValue = $contractSummary['original_owner_value']->plus($approvedOwnerChanges);
        $revisedCommitments = $contractSummary['original_commitments']->plus($approvedSubcontractChanges);
        $grossMargin = $revisedOwnerValue->minus($revisedCommitments);
        $clientInvoiced = $this->clientInvoiced($project);
        $subcontractorInvoiced = $this->subcontractorInvoiced($project);
        $received = $this->payments($project, InvoiceType::Client);
        $paid = $this->payments($project, InvoiceType::Subcontractor);

        return [
            ...$contractSummary,
            'approved_owner_changes' => $approvedOwnerChanges,
            'approved_subcontract_changes' => $approvedSubcontractChanges,
            'revised_owner_value' => $revisedOwnerValue,
            'revised_commitments' => $revisedCommitments,
            'gross_margin' => $grossMargin,
            'gross_margin_percentage' => $revisedOwnerValue->cents === 0
                ? 0.0
                : round(($grossMargin->cents / $revisedOwnerValue->cents) * 100, 2),
            'client_invoiced' => $clientInvoiced,
            'remaining_to_bill' => $revisedOwnerValue->minus($clientInvoiced),
            'subcontractor_invoiced' => $subcontractorInvoiced,
            'remaining_commitment' => $revisedCommitments->minus($subcontractorInvoiced),
            'received' => $received,
            'paid' => $paid,
            'receivable' => $this->outstanding($project, InvoiceType::Client)->minus($received),
            'payable' => $this->outstanding($project, InvoiceType::Subcontractor)->minus($paid),
        ];
    }

    /**
     * @return array{owner: Money, subcontract: Money}
     */
    private function approvedChanges(Project $project): array
    {
        $committedContractStatuses = array_map(
            fn (ContractStatus $status): string => $status->value,
            array_filter(ContractStatus::cases(), fn (ContractStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );

        $totals = ChangeOrder::query()
            ->whereBelongsTo($project)
            ->where('status', ChangeOrderStatus::Approved)
            ->whereHas('contract', fn ($query) => $query->whereIn('status', $committedContractStatuses))
            ->withSum('lineItems as total_amount', 'amount')
            ->get()
            ->groupBy(fn (ChangeOrder $changeOrder): string => $changeOrder->type->value)
            ->map(fn ($changeOrders): int => (int) $changeOrders->sum('total_amount'));

        return [
            ChangeOrderType::Owner->value => Money::fromCents((int) $totals->get(ChangeOrderType::Owner->value, 0)),
            ChangeOrderType::Subcontract->value => Money::fromCents((int) $totals->get(ChangeOrderType::Subcontract->value, 0)),
        ];
    }

    private function clientInvoiced(Project $project): Money
    {
        $includedStatuses = array_map(
            fn (InvoiceStatus $status): string => $status->value,
            array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );

        return Money::fromCents((int) Invoice::query()
            ->whereBelongsTo($project)
            ->where('type', InvoiceType::Client)
            ->whereIn('status', $includedStatuses)
            ->sum('subtotal'));
    }

    private function subcontractorInvoiced(Project $project): Money
    {
        $includedStatuses = array_map(
            fn (InvoiceStatus $status): string => $status->value,
            array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );

        return Money::fromCents((int) Invoice::query()
            ->whereBelongsTo($project)
            ->where('type', InvoiceType::Subcontractor)
            ->whereIn('status', $includedStatuses)
            ->sum('subtotal'));
    }

    private function payments(Project $project, InvoiceType $invoiceType): Money
    {
        return Money::fromCents((int) Payment::query()
            ->whereBelongsTo($project)
            ->where('direction', $invoiceType->paymentDirection())
            ->sum('amount'));
    }

    private function outstanding(Project $project, InvoiceType $invoiceType): Money
    {
        $includedStatuses = array_map(
            fn (InvoiceStatus $status): string => $status->value,
            array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );

        return Money::fromCents((int) Invoice::query()
            ->whereBelongsTo($project)
            ->where('type', $invoiceType)
            ->whereIn('status', $includedStatuses)
            ->sum('total'));
    }
}
