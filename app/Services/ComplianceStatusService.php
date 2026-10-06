<?php

namespace App\Services;

use App\Enums\ComplianceStatus;
use App\Models\ComplianceRecord;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ComplianceStatusService
{
    public function statusFor(ComplianceRecord $record, ?Carbon $today = null): ComplianceStatus
    {
        $today ??= now()->startOfDay();

        if ($record->expires_at === null) {
            return ComplianceStatus::Missing;
        }

        if ($record->expires_at->lt($today)) {
            return ComplianceStatus::Expired;
        }

        $warningDays = $record->requirement->default_reminder_days ?? app(CompanySettings::class)->complianceWarningDays();

        return $record->expires_at->lte($today->copy()->addDays($warningDays))
            ? ComplianceStatus::ExpiringSoon
            : ComplianceStatus::Valid;
    }

    public function refresh(ComplianceRecord $record, ?Carbon $today = null): ComplianceRecord
    {
        $record->update(['status' => $this->statusFor($record, $today)]);

        return $record->fresh(['requirement']);
    }

    public function verify(ComplianceRecord $record, User $actor): ComplianceRecord
    {
        Gate::forUser($actor)->authorize('verify', $record);

        $record->update([
            'verified_by' => $actor->getKey(),
            'verified_at' => now(),
            'status' => $this->statusFor($record),
        ]);

        return $record->fresh(['requirement']);
    }

    /** @return array<int, ComplianceRecord> */
    public function warningsFor(User $user): array
    {
        if (! $user->can('view_compliance')) {
            return [];
        }

        return ComplianceRecord::query()
            ->with(['requirement:id,name,default_reminder_days', 'project:id'])
            ->whereHas('requirement', fn ($query) => $query->where('is_active', true))
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhereDate('expires_at', '<=', now()->addDays(app(CompanySettings::class)->complianceWarningDays()));
            })
            ->when(! $user->can('view_all_projects'), function ($query) use ($user): void {
                $query->where(function ($records) use ($user): void {
                    $records->whereNull('project_id')
                        ->orWhereHas('project', function ($projects) use ($user): void {
                            /** @var Builder<Project> $projects */
                            $projects->visibleTo($user);
                        });
                });
            })
            ->get()
            ->filter(fn (ComplianceRecord $record): bool => $user->can('view', $record) && $this->statusFor($record)->needsAttention())
            ->values()
            ->all();
    }
}
