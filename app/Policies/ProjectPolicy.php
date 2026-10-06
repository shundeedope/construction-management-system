<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->vendor_id !== null || $user->can(Permission::ViewAnyProject->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->vendor_id !== null
            ? $project->contracts()->where('vendor_id', $user->vendor_id)->exists()
            : $user->can(Permission::ViewAnyProject->value) && $user->hasProjectAccess($project);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreateProject->value);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can(Permission::UpdateProject->value)
            && $user->hasProjectAccess($project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can(Permission::DeleteProject->value)
            && $user->hasProjectAccess($project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->delete($user, $project);
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Gate for every financial figure shown on the project: contracts, change orders,
     * invoices, payments, margin, and financial documents all defer to this.
     */
    public function viewFinancials(User $user, Project $project): bool
    {
        return $user->canViewFinancialsFor($project);
    }

    public function manageTeam(User $user, Project $project): bool
    {
        return $user->can(Permission::ManageProjectTeam->value)
            && $user->hasProjectAccess($project);
    }
}
