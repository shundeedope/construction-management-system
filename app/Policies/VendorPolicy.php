<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrganizations->value);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->can(Permission::ViewOrganizations->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOrganizations->value);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $user->can(Permission::ManageOrganizations->value);
    }

    /**
     * A vendor holding contracts is financial history and must not be deleted.
     */
    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->can(Permission::ManageOrganizations->value)
            && ! $vendor->contracts()->exists();
    }
}
