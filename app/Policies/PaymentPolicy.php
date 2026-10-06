<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permission::ViewFinancials->value,
            Permission::ViewAllFinancials->value,
        ]);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->canViewFinancialsFor($payment->project);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManagePayments->value);
    }

    /**
     * Payments are never edited in place. A mistake is corrected by removing the payment,
     * which soft-deletes it and recalculates the invoice status, then recording a new one.
     */
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can(Permission::ManagePayments->value)
            && $user->canViewFinancialsFor($payment->project);
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
