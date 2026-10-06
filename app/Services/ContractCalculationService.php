<?php

namespace App\Services;

use App\Enums\ChangeOrderStatus;
use App\Enums\ContractType;
use App\Models\ChangeOrderLineItem;
use App\Models\Contract;
use App\Models\Project;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractCalculationService
{
    public function originalAmount(Contract $contract): Money
    {
        return Money::fromCents((int) $contract->lineItems()->sum('amount'));
    }

    public function retention(Contract $contract, ?Money $amount = null): Money
    {
        return ($amount ?? $this->originalAmount($contract))->basisPoints($contract->retention_basis_points);
    }

    public function approvedChangeAmount(Contract $contract): Money
    {
        return Money::fromCents((int) ChangeOrderLineItem::query()
            ->whereHas('changeOrder', fn ($query) => $query
                ->whereBelongsTo($contract)
                ->where('status', ChangeOrderStatus::Approved))
            ->sum('amount'));
    }

    public function revisedAmount(Contract $contract): Money
    {
        return Money::fromCents((int) $contract->original_amount)->plus($this->approvedChangeAmount($contract));
    }

    public function syncOriginalAmount(Contract $contract): Contract
    {
        return DB::transaction(function () use ($contract): Contract {
            /** @var Contract $lockedContract */
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->getKey());
            $this->ensureLineItemsAreNonNegative($lockedContract);
            $this->ensureOrganizationInvariants($lockedContract);

            $lockedContract->update(['original_amount' => $this->originalAmount($lockedContract)->cents]);

            return $lockedContract->fresh();
        });
    }

    public function ensureOrganizationInvariants(Contract $contract): void
    {
        $project = $contract->relationLoaded('project') ? $contract->project : Project::query()->findOrFail($contract->project_id);

        if ($contract->type === ContractType::Owner) {
            if ($contract->client_id !== $project->client_id || $contract->vendor_id !== null) {
                throw ValidationException::withMessages([
                    'client_id' => 'An owner contract must use the project client and cannot have a vendor.',
                ]);
            }

            return;
        }

        if ($contract->vendor_id === null || $contract->client_id !== null) {
            throw ValidationException::withMessages([
                'vendor_id' => 'A subcontract or purchase order requires a vendor and cannot have a client.',
            ]);
        }
    }

    public function ensureLineItemsAreNonNegative(Contract $contract): void
    {
        $hasNegativeLine = $contract->lineItems()->where('amount', '<', 0)->exists();

        if ($hasNegativeLine) {
            throw ValidationException::withMessages([
                'line_items' => 'Schedule of Values line amounts cannot be negative.',
            ]);
        }
    }

    public function assertLineAmount(int $amount): void
    {
        if ($amount < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Schedule of Values line amounts cannot be negative.',
            ]);
        }
    }
}
