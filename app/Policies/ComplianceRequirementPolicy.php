<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ComplianceRequirement;
use App\Models\User;

class ComplianceRequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCompliance->value);
    }

    public function view(User $user, ComplianceRequirement $requirement): bool
    {
        return $user->can(Permission::ViewCompliance->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCompliance->value);
    }

    public function update(User $user, ComplianceRequirement $requirement): bool
    {
        return $user->can(Permission::ManageCompliance->value);
    }

    /**
     * A requirement with recorded evidence is history and must be deactivated, not deleted.
     */
    public function delete(User $user, ComplianceRequirement $requirement): bool
    {
        return $user->can(Permission::ManageCompliance->value)
            && ! $requirement->records()->exists();
    }
}
