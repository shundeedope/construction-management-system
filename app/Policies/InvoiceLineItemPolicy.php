<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\User;

/**
 * Progress-billing lines are governed entirely by their invoice, so they lock as soon as the
 * invoice leaves draft.
 */
class InvoiceLineItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny', Invoice::class);
    }

    public function view(User $user, InvoiceLineItem $lineItem): bool
    {
        return $user->can('view', $lineItem->invoice);
    }

    public function create(User $user): bool
    {
        return $user->can('create', Invoice::class);
    }

    public function update(User $user, InvoiceLineItem $lineItem): bool
    {
        return $user->can('update', $lineItem->invoice);
    }

    public function delete(User $user, InvoiceLineItem $lineItem): bool
    {
        return $user->can('update', $lineItem->invoice);
    }
}
