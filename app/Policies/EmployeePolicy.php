<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permission::ManageEmployees->value,
            Permission::ViewAllTimeEntries->value,
        ]);
    }

    /**
     * Employees may always see their own record; seeing anybody else's is an HR capability.
     */
    public function view(User $user, Employee $employee): bool
    {
        return $employee->user_id === $user->getKey()
            || $user->canAny([
                Permission::ManageEmployees->value,
                Permission::ViewAllTimeEntries->value,
            ]);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageEmployees->value);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can(Permission::ManageEmployees->value);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can(Permission::ManageEmployees->value);
    }
}
