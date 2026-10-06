<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CompanySetting;
use App\Models\User;

class CompanySettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function view(User $user, CompanySetting $setting): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function update(User $user, CompanySetting $setting): bool
    {
        return $user->can(Permission::ManageSettings->value);
    }

    public function delete(User $user, CompanySetting $setting): bool
    {
        return false;
    }
}
