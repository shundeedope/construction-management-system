<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrganizations->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can(Permission::ViewOrganizations->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOrganizations->value);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can(Permission::ManageOrganizations->value);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can(Permission::ManageOrganizations->value)
            && ! $client->projects()->exists();
    }
}
