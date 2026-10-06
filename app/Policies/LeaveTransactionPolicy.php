<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LeaveTransaction;
use App\Models\User;

class LeaveTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permission::ViewOwnLeave->value,
            Permission::ManageLeave->value,
        ]);
    }

    public function view(User $user, LeaveTransaction $transaction): bool
    {
        if ($user->can(Permission::ManageLeave->value)) {
            return true;
        }

        return $user->can(Permission::ViewOwnLeave->value)
            && $transaction->employee->user_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageLeave->value);
    }

    /**
     * The leave ledger is append-only: a balance is never overwritten, and a correction is
     * recorded as a new adjustment transaction.
     */
    public function update(User $user, LeaveTransaction $transaction): bool
    {
        return false;
    }

    public function delete(User $user, LeaveTransaction $transaction): bool
    {
        return false;
    }
}
