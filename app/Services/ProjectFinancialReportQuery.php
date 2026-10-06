<?php

namespace App\Services;

use App\Enums\ChangeOrderStatus;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class ProjectFinancialReportQuery
{
    /**
     * Add portable aggregate columns used by financial project tables and reports.
     *
     * @param  Builder<Project>  $projects
     * @return Builder<Project>
     */
    public function apply(Builder $projects): Builder
    {
        $committedStatuses = array_map(
            fn (ContractStatus $status): string => $status->value,
            array_filter(ContractStatus::cases(), fn (ContractStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );

        $ownerContracts = Contract::query()->select('project_id')->selectRaw('SUM(original_amount) as amount')->where('type', ContractType::Owner)->whereIn('status', $committedStatuses)->groupBy('project_id');
        $commitments = Contract::query()->select('project_id')->selectRaw('SUM(original_amount) as amount')->whereIn('type', [ContractType::Subcontract, ContractType::PurchaseOrder])->whereIn('status', $committedStatuses)->groupBy('project_id');

        return $projects
            ->leftJoinSub($ownerContracts, 'owner_contract_totals', 'owner_contract_totals.project_id', '=', 'projects.id')
            ->leftJoinSub($commitments, 'commitment_totals', 'commitment_totals.project_id', '=', 'projects.id')
            ->leftJoinSub($this->approvedChanges(ContractType::Owner, $committedStatuses), 'owner_change_totals', 'owner_change_totals.project_id', '=', 'projects.id')
            ->leftJoinSub($this->approvedChanges(ContractType::Subcontract, $committedStatuses), 'commitment_change_totals', 'commitment_change_totals.project_id', '=', 'projects.id')
            ->addSelect('projects.*')
            ->selectRaw('COALESCE(owner_contract_totals.amount, 0) + COALESCE(owner_change_totals.amount, 0) as revised_owner_value')
            ->selectRaw('COALESCE(commitment_totals.amount, 0) + COALESCE(commitment_change_totals.amount, 0) as revised_commitments')
            ->selectRaw('(COALESCE(owner_contract_totals.amount, 0) + COALESCE(owner_change_totals.amount, 0)) - (COALESCE(commitment_totals.amount, 0) + COALESCE(commitment_change_totals.amount, 0)) as gross_margin');
    }

    /**
     * @param  array<int, string>  $committedStatuses
     * @return Builder<ChangeOrder>
     */
    private function approvedChanges(ContractType $type, array $committedStatuses): Builder
    {
        return ChangeOrder::query()
            ->select('change_orders.project_id')
            ->selectRaw('SUM(change_order_line_items.amount) as amount')
            ->join('change_order_line_items', 'change_order_line_items.change_order_id', '=', 'change_orders.id')
            ->join('contracts', 'contracts.id', '=', 'change_orders.contract_id')
            ->where('change_orders.type', $type)
            ->where('change_orders.status', ChangeOrderStatus::Approved)
            ->whereIn('contracts.status', $committedStatuses)
            ->whereNull('contracts.deleted_at')
            ->groupBy('change_orders.project_id');
    }
}
