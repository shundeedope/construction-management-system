<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\User;

/**
 * Schedule of Values lines carry no rules of their own: they are governed entirely by the
 * contract they belong to, so an executed contract's lines are locked with it.
 */
class ContractLineItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewAny', Contract::class);
    }

    public function view(User $user, ContractLineItem $lineItem): bool
    {
        return $user->can('view', $lineItem->contract);
    }

    public function create(User $user): bool
    {
        return $user->can('create', Contract::class);
    }

    public function update(User $user, ContractLineItem $lineItem): bool
    {
        return $user->can('update', $lineItem->contract);
    }

    public function delete(User $user, ContractLineItem $lineItem): bool
    {
        return $user->can('update', $lineItem->contract);
    }
}
