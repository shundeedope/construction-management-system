<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ComplianceRecord;
use App\Models\User;

class ComplianceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewCompliance->value);
    }

    /**
     * A project-scoped compliance record is only visible to users with access to that project;
     * employee and vendor records are visible to anybody who may view compliance.
     */
    public function view(User $user, ComplianceRecord $record): bool
    {
        if (! $user->can(Permission::ViewCompliance->value)) {
            return false;
        }

        return $record->project === null
            || $user->hasProjectAccess($record->project);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCompliance->value);
    }

    public function update(User $user, ComplianceRecord $record): bool
    {
        return $user->can(Permission::ManageCompliance->value)
            && $this->view($user, $record);
    }

    public function delete(User $user, ComplianceRecord $record): bool
    {
        return $this->update($user, $record);
    }

    public function verify(User $user, ComplianceRecord $record): bool
    {
        return $user->can(Permission::VerifyCompliance->value)
            && $this->view($user, $record);
    }
}
