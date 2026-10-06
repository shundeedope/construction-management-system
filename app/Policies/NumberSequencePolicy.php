<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\NumberSequence;
use App\Models\User;

class NumberSequencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function view(User $user, NumberSequence $sequence): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    /**
     * Formats and padding are editable, but a counter is never deleted: reusing a consumed
     * number would break the uniqueness of documents already issued.
     */
    public function update(User $user, NumberSequence $sequence): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function delete(User $user, NumberSequence $sequence): bool
    {
        return false;
    }
}
