<?php

namespace App\Services;

use App\Enums\TimeEntryStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TimeEntryApprovalService
{
    /** @param array{project_id: int, work_date: string, regular_hours: numeric, overtime_hours: numeric, is_billable?: bool, description?: string|null} $attributes */
    public function create(Employee $employee, array $attributes, User $actor): TimeEntry
    {
        Gate::forUser($actor)->authorize('create', TimeEntry::class);

        if ($employee->user_id !== $actor->getKey()) {
            throw new AuthorizationException;
        }

        $project = Project::query()->findOrFail($attributes['project_id']);

        if (! $actor->isAssignedTo($project)) {
            throw new AuthorizationException;
        }

        $this->validateHours($attributes);
        $this->ensureNoDuplicate($employee, $project, $attributes['work_date']);

        return TimeEntry::query()->create([
            ...$attributes,
            'employee_id' => $employee->getKey(),
            'status' => TimeEntryStatus::Draft,
        ]);
    }

    public function submit(TimeEntry $entry, User $actor): TimeEntry
    {
        return DB::transaction(function () use ($entry, $actor): TimeEntry {
            $lockedEntry = $this->lockForTransition($entry, $actor, 'submit');
            $lockedEntry->update([
                'status' => TimeEntryStatus::Submitted,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            return $lockedEntry->fresh();
        });
    }

    public function approve(TimeEntry $entry, User $actor): TimeEntry
    {
        return DB::transaction(function () use ($entry, $actor): TimeEntry {
            $lockedEntry = $this->lockForTransition($entry, $actor, 'approve');
            $lockedEntry->update([
                'status' => TimeEntryStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
                'rejection_reason' => null,
            ]);

            return $lockedEntry->fresh();
        });
    }

    public function reject(TimeEntry $entry, User $actor, string $reason): TimeEntry
    {
        if (mb_trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'A rejection reason is required.']);
        }

        return DB::transaction(function () use ($entry, $actor, $reason): TimeEntry {
            $lockedEntry = $this->lockForTransition($entry, $actor, 'reject');
            $lockedEntry->update([
                'status' => TimeEntryStatus::Rejected,
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => $reason,
            ]);

            return $lockedEntry->fresh();
        });
    }

    /** @param array{work_date: string, regular_hours: numeric, overtime_hours: numeric} $attributes */
    private function validateHours(array $attributes): void
    {
        $regularHours = (float) $attributes['regular_hours'];
        $overtimeHours = (float) $attributes['overtime_hours'];
        $maximumDailyHours = app(CompanySettings::class)->maximumDailyHours();

        if ($regularHours < 0 || $overtimeHours < 0 || ($regularHours + $overtimeHours) > $maximumDailyHours) {
            throw ValidationException::withMessages([
                'regular_hours' => "Hours must be non-negative and total no more than {$maximumDailyHours} per day.",
            ]);
        }
    }

    private function ensureNoDuplicate(Employee $employee, Project $project, string $workDate): void
    {
        if (TimeEntry::query()->whereBelongsTo($employee)->whereBelongsTo($project)->whereDate('work_date', Carbon::parse($workDate))->exists()) {
            throw ValidationException::withMessages(['work_date' => 'A time entry already exists for this project and date.']);
        }
    }

    private function lockForTransition(TimeEntry $entry, User $actor, string $ability): TimeEntry
    {
        /** @var TimeEntry $lockedEntry */
        $lockedEntry = TimeEntry::query()->with(['employee', 'project'])->lockForUpdate()->findOrFail($entry->getKey());

        Gate::forUser($actor)->authorize($ability, $lockedEntry);

        return $lockedEntry;
    }
}
