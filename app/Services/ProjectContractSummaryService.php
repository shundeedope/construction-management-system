<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Project;
use App\Support\Money;

class ProjectContractSummaryService
{
    /**
     * @return array{original_owner_value: Money, original_commitments: Money}
     */
    public function summary(Project $project): array
    {
        $committedStatuses = array_map(
            fn (ContractStatus $status): string => $status->value,
            array_filter(ContractStatus::cases(), fn (ContractStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );

        return [
            'original_owner_value' => Money::fromCents((int) $project->contracts()
                ->where('type', ContractType::Owner)
                ->whereIn('status', $committedStatuses)
                ->sum('original_amount')),
            'original_commitments' => Money::fromCents((int) $project->contracts()
                ->whereIn('type', [ContractType::Subcontract, ContractType::PurchaseOrder])
                ->whereIn('status', $committedStatuses)
                ->sum('original_amount')),
        ];
    }
}
