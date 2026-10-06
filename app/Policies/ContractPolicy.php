<?php

namespace App\Policies;

use App\Enums\ContractStatus;
use App\Enums\Permission;
use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->vendor_id !== null) {
            return true;
        }

        return $user->canAny([
            Permission::ViewFinancials->value,
            Permission::ViewAllFinancials->value,
        ]);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->ownsVendorContract($contract)
            || $user->canViewFinancialsFor($contract->project);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageContracts->value);
    }

    /**
     * Executed contracts are financially frozen: revisions must go through a change order.
     */
    public function update(User $user, Contract $contract): bool
    {
        return $user->can(Permission::ManageContracts->value)
            && $user->canViewFinancialsFor($contract->project)
            && ! $contract->status->isFinancesLocked();
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->can(Permission::ManageContracts->value)
            && $user->canViewFinancialsFor($contract->project)
            && $contract->status === ContractStatus::Draft;
    }

    public function execute(User $user, Contract $contract): bool
    {
        return $user->can(Permission::ExecuteContracts->value)
            && $user->canViewFinancialsFor($contract->project)
            && $contract->status->canTransitionTo(ContractStatus::Executed);
    }

    public function close(User $user, Contract $contract): bool
    {
        return $user->can(Permission::ExecuteContracts->value)
            && $user->canViewFinancialsFor($contract->project)
            && $contract->status->canTransitionTo(ContractStatus::Closed);
    }

    public function cancel(User $user, Contract $contract): bool
    {
        return $user->can(Permission::ExecuteContracts->value)
            && $user->canViewFinancialsFor($contract->project)
            && $contract->status->canTransitionTo(ContractStatus::Cancelled);
    }
}
