<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ClientContact;
use App\Models\User;

class ClientContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewOrganizations->value);
    }

    public function view(User $user, ClientContact $contact): bool
    {
        return $user->can('view', $contact->client);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOrganizations->value);
    }

    public function update(User $user, ClientContact $contact): bool
    {
        return $user->can('update', $contact->client);
    }

    public function delete(User $user, ClientContact $contact): bool
    {
        return $user->can('update', $contact->client);
    }
}
