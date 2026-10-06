<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permission::ManageOwnTimeEntries->value,
            Permission::ViewAllTimeEntries->value,
        ]);
    }

    /**
     * An entry is visible to the employee who logged it, and to reviewers on that project.
     */
    public function view(User $user, TimeEntry $timeEntry): bool
    {
        if ($this->isOwnedBy($user, $timeEntry)) {
            return true;
        }

        return $user->can(Permission::ViewAllTimeEntries->value)
            && $user->hasProjectAccess($timeEntry->project);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnTimeEntries->value);
    }

    /**
     * Employees edit only their own unapproved entries. Approved time is locked; a manager
     * must reject it back into the workflow before it can change.
     */
    public function update(User $user, TimeEntry $timeEntry): bool
    {
        return $this->isOwnedBy($user, $timeEntry)
            && $user->can(Permission::ManageOwnTimeEntries->value)
            && ! $timeEntry->status->isLocked();
    }

    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        return $this->update($user, $timeEntry)
            && $timeEntry->status === TimeEntryStatus::Draft;
    }

    public function submit(User $user, TimeEntry $timeEntry): bool
    {
        return $this->isOwnedBy($user, $timeEntry)
            && $user->can(Permission::ManageOwnTimeEntries->value)
            && $timeEntry->status->canTransitionTo(TimeEntryStatus::Submitted);
    }

    public function approve(User $user, TimeEntry $timeEntry): bool
    {
        return $user->can(Permission::ApproveTimeEntries->value)
            && $user->hasProjectAccess($timeEntry->project)
            && ! $this->isOwnedBy($user, $timeEntry)
            && $timeEntry->status->canTransitionTo(TimeEntryStatus::Approved);
    }

    public function reject(User $user, TimeEntry $timeEntry): bool
    {
        return $user->can(Permission::ApproveTimeEntries->value)
            && $user->hasProjectAccess($timeEntry->project)
            && ! $this->isOwnedBy($user, $timeEntry)
            && $timeEntry->status->canTransitionTo(TimeEntryStatus::Rejected);
    }

    private function isOwnedBy(User $user, TimeEntry $timeEntry): bool
    {
        return $timeEntry->employee->user_id === $user->getKey();
    }
}
