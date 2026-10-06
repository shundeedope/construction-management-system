<?php

namespace App\Policies;

use App\Enums\ChangeOrderStatus;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderLineItem;
use App\Models\User;

/**
 * Change-order lines are governed entirely by their change order, so they lock as soon as
 * the change order is submitted.
 */
class ChangeOrderLineItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny', ChangeOrder::class);
    }

    public function view(User $user, ChangeOrderLineItem $lineItem): bool
    {
        return $user->can('view', $lineItem->changeOrder);
    }

    public function create(User $user): bool
    {
        return $user->can('create', ChangeOrder::class);
    }

    public function update(User $user, ChangeOrderLineItem $lineItem): bool
    {
        return $lineItem->changeOrder->status === ChangeOrderStatus::Draft
            && $user->can('update', $lineItem->changeOrder);
    }

    public function delete(User $user, ChangeOrderLineItem $lineItem): bool
    {
        return $lineItem->changeOrder->status === ChangeOrderStatus::Draft
            && $user->can('update', $lineItem->changeOrder);
    }
}
