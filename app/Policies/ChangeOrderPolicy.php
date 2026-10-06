<?php

namespace App\Policies;

use App\Enums\ChangeOrderStatus;
use App\Enums\Permission;
use App\Models\ChangeOrder;
use App\Models\User;

class ChangeOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permission::ViewFinancials->value,
            Permission::ViewAllFinancials->value,
        ]);
    }

    public function view(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->canViewFinancialsFor($changeOrder->project);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageChangeOrders->value);
    }

    /**
     * Drafts are editable. Submitted records may enter the review page only so an approver can
     * act; the page itself rejects any attempted form save once the workflow has started.
     */
    public function update(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->can(Permission::ManageChangeOrders->value)
            && $user->canViewFinancialsFor($changeOrder->project)
            && in_array($changeOrder->status, [ChangeOrderStatus::Draft, ChangeOrderStatus::Submitted], strict: true);
    }

    public function delete(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->can(Permission::ManageChangeOrders->value)
            && $user->canViewFinancialsFor($changeOrder->project)
            && $changeOrder->status === ChangeOrderStatus::Draft;
    }

    public function submit(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->can(Permission::ManageChangeOrders->value)
            && $user->canViewFinancialsFor($changeOrder->project)
            && $changeOrder->status->canTransitionTo(ChangeOrderStatus::Submitted);
    }

    public function approve(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->can(Permission::ApproveChangeOrders->value)
            && $user->canViewFinancialsFor($changeOrder->project)
            && ($changeOrder->status === ChangeOrderStatus::Approved || $changeOrder->status->canTransitionTo(ChangeOrderStatus::Approved));
    }

    public function reject(User $user, ChangeOrder $changeOrder): bool
    {
        return $user->can(Permission::ApproveChangeOrders->value)
            && $user->canViewFinancialsFor($changeOrder->project)
            && $changeOrder->status->canTransitionTo(ChangeOrderStatus::Rejected);
    }
}
