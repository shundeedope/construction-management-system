<?php

namespace App\Services;

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Enums\TimeEntryStatus;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderLineItem;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

class DashboardMetricsService
{
    /**
     * @return array{active_projects: int, revised_value: Money, receivables: Money, payables: Money, margin: Money}
     */
    public function financial(User $user): array
    {
        $projects = Project::query()->financiallyVisibleTo($user);
        $projectIds = (clone $projects)->select('id');
        $committedStatuses = $this->committedContractStatuses();

        $originalOwnerValue = (int) Contract::query()
            ->whereIn('project_id', $projectIds)
            ->where('type', ContractType::Owner)
            ->whereIn('status', $committedStatuses)
            ->sum('original_amount');
        $originalCommitments = (int) Contract::query()
            ->whereIn('project_id', $projectIds)
            ->whereIn('type', [ContractType::Subcontract, ContractType::PurchaseOrder])
            ->whereIn('status', $committedStatuses)
            ->sum('original_amount');
        $approvedOwnerChanges = $this->approvedChangeTotal($projectIds, ChangeOrderType::Owner, $committedStatuses);
        $approvedSubcontractChanges = $this->approvedChangeTotal($projectIds, ChangeOrderType::Subcontract, $committedStatuses);
        $revisedValue = Money::fromCents($originalOwnerValue + $approvedOwnerChanges);
        $revisedCommitments = Money::fromCents($originalCommitments + $approvedSubcontractChanges);

        return [
            'active_projects' => (clone $projects)->where('status', ProjectStatus::Active)->count(),
            'revised_value' => $revisedValue,
            'receivables' => $this->outstanding($projectIds, InvoiceType::Client),
            'payables' => $this->outstanding($projectIds, InvoiceType::Subcontractor),
            'margin' => $revisedValue->minus($revisedCommitments),
        ];
    }

    /**
     * @return array{overdue_invoices: int, pending_changes: int, pending_timesheets: int, expiring_compliance: int}
     */
    public function attention(User $user): array
    {
        $financialProjectIds = Project::query()->financiallyVisibleTo($user)->select('id');
        $visibleProjectIds = Project::query()->visibleTo($user)->select('id');
        $invoiceStatuses = [InvoiceStatus::Approved, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid];

        return [
            'overdue_invoices' => $user->can(Permission::ViewFinancials->value)
                ? Invoice::query()->whereIn('project_id', $financialProjectIds)->where('type', InvoiceType::Client)->whereIn('status', $invoiceStatuses)->whereDate('due_date', '<', today())->count()
                : 0,
            'pending_changes' => $user->can(Permission::ViewFinancials->value)
                ? ChangeOrder::query()->whereIn('project_id', $financialProjectIds)->where('status', ChangeOrderStatus::Submitted)->count()
                : 0,
            'pending_timesheets' => $user->can(Permission::ApproveTimeEntries->value)
                ? TimeEntry::query()->whereIn('project_id', $visibleProjectIds)->where('status', TimeEntryStatus::Submitted)->count()
                : 0,
            'expiring_compliance' => $user->can(Permission::ViewCompliance->value)
                ? count(app(ComplianceStatusService::class)->warningsFor($user))
                : 0,
        ];
    }

    /**
     * @param  Builder<Project>  $projectIds
     * @param  array<int, string>  $committedStatuses
     */
    private function approvedChangeTotal(Builder $projectIds, ChangeOrderType $type, array $committedStatuses): int
    {
        return (int) ChangeOrderLineItem::query()
            ->whereHas('changeOrder', fn (Builder $changeOrders) => $changeOrders
                ->whereIn('project_id', $projectIds)
                ->where('type', $type)
                ->where('status', ChangeOrderStatus::Approved)
                ->whereHas('contract', fn (Builder $contracts) => $contracts->whereIn('status', $committedStatuses)))
            ->sum('amount');
    }

    /** @param Builder<Project> $projectIds */
    private function outstanding(Builder $projectIds, InvoiceType $type): Money
    {
        $includedStatuses = array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->countsTowardsProjectFinancials());
        $total = (int) Invoice::query()
            ->whereIn('project_id', $projectIds)
            ->where('type', $type)
            ->whereIn('status', $includedStatuses)
            ->sum('total');
        $received = (int) Payment::query()
            ->whereIn('project_id', $projectIds)
            ->where('direction', $type->paymentDirection())
            ->whereHas('invoice', fn (Builder $invoices) => $invoices
                ->where('type', $type)
                ->whereIn('status', $includedStatuses))
            ->sum('amount');

        return Money::fromCents($total - $received);
    }

    /** @return array<int, string> */
    private function committedContractStatuses(): array
    {
        return array_map(
            fn (ContractStatus $status): string => $status->value,
            array_filter(ContractStatus::cases(), fn (ContractStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );
    }
}
