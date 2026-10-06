<?php

namespace App\Filament\Pages;

use App\Enums\TimeEntryStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\CompanySettings;
use App\Services\TimeEntryApprovalService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WeeklyTimesheet extends Page
{
    protected string $view = 'filament.pages.weekly-timesheet';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    public string $weekOf;

    /**
     * Regular hours keyed by project id, then work date (Y-m-d).
     *
     * @var array<int, array<string, string>>
     */
    public array $hours = [];

    /** @var Collection<string, TimeEntry>|null */
    protected ?Collection $cachedWeekEntries = null;

    public function mount(): void
    {
        $this->weekOf = now()->startOfWeek()->toDateString();
        $this->loadHours();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_own_time_entries') === true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveDraft')
                ->label('Save draft')
                ->color('gray')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->employee() !== null)
                ->action(fn () => $this->saveDraft()),
            Action::make('submitWeek')
                ->label('Submit week')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription('Submitted hours are sent for approval and can no longer be edited here.')
                ->visible(fn (): bool => $this->employee() !== null)
                ->action(fn () => $this->submitWeek()),
        ];
    }

    public function updatedWeekOf(): void
    {
        $this->weekOf = CarbonImmutable::parse($this->weekOf)->startOfWeek()->toDateString();
        $this->refreshGrid();
    }

    public function previousWeek(): void
    {
        $this->weekOf = CarbonImmutable::parse($this->weekOf)->subWeek()->toDateString();
        $this->refreshGrid();
    }

    public function nextWeek(): void
    {
        $this->weekOf = CarbonImmutable::parse($this->weekOf)->addWeek()->toDateString();
        $this->refreshGrid();
    }

    public function saveDraft(): bool
    {
        $employee = $this->employee();

        if ($employee === null) {
            return false;
        }

        /** @var User $user */
        $user = auth()->user();
        $service = app(TimeEntryApprovalService::class);
        $maximumDailyHours = app(CompanySettings::class)->maximumDailyHours();
        $entries = $this->weekEntries();

        try {
            foreach ($this->hours as $projectId => $days) {
                foreach ($days as $date => $value) {
                    $entry = $entries->get("{$projectId}|{$date}");
                    $newHours = blank($value) ? 0.0 : (float) $value;

                    if ($entry !== null && ! $this->isEditable($entry)) {
                        continue;
                    }

                    if ($newHours < 0 || $newHours > $maximumDailyHours) {
                        throw ValidationException::withMessages([
                            'hours' => "Hours for {$date} must be between 0 and {$maximumDailyHours}.",
                        ]);
                    }

                    if ($entry === null && $newHours > 0) {
                        $service->create($employee, [
                            'project_id' => (int) $projectId,
                            'work_date' => $date,
                            'regular_hours' => $newHours,
                            'overtime_hours' => 0,
                        ], $user);
                    } elseif ($entry !== null && $newHours <= 0 && $entry->status === TimeEntryStatus::Draft) {
                        Gate::authorize('delete', $entry);
                        $entry->delete();
                    } elseif ($entry !== null && abs((float) $entry->regular_hours - $newHours) >= 0.005) {
                        Gate::authorize('update', $entry);
                        $entry->update(['regular_hours' => $newHours]);
                    }
                }
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Timesheet could not be saved')
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->send();
            $this->refreshGrid();

            return false;
        }

        $this->refreshGrid();

        return true;
    }

    public function submitWeek(): void
    {
        if (! $this->saveDraft()) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();
        $service = app(TimeEntryApprovalService::class);
        $submitted = 0;

        foreach ($this->weekEntries() as $entry) {
            if (in_array($entry->status, [TimeEntryStatus::Draft, TimeEntryStatus::Rejected], strict: true)) {
                $service->submit($entry, $user);
                $submitted++;
            }
        }

        $this->refreshGrid();

        Notification::make()
            ->success()
            ->title($submitted > 0 ? "Submitted {$submitted} ".str('entry')->plural($submitted).' for approval' : 'Nothing to submit for this week')
            ->send();
    }

    /** @return Collection<int, CarbonImmutable> */
    public function weekDays(): Collection
    {
        $start = CarbonImmutable::parse($this->weekOf)->startOfWeek();

        return collect(range(0, 6))->map(fn (int $day): CarbonImmutable => $start->addDays($day));
    }

    /** @return Collection<int, Project> */
    public function gridProjects(): Collection
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || $this->employee() === null) {
            return collect();
        }

        $assigned = $user->projects()->get(['projects.id', 'projects.job_number', 'projects.name']);
        $withEntries = Project::query()
            ->whereIn('id', $this->weekEntries()->pluck('project_id')->unique())
            ->get(['id', 'job_number', 'name']);

        return $assigned->concat($withEntries)->unique('id')->sortBy('job_number')->values();
    }

    /** @return Collection<string, TimeEntry> */
    public function weekEntries(): Collection
    {
        if ($this->cachedWeekEntries !== null) {
            return $this->cachedWeekEntries;
        }

        $employee = $this->employee();

        if ($employee === null) {
            return collect();
        }

        $start = CarbonImmutable::parse($this->weekOf)->startOfWeek();

        return $this->cachedWeekEntries = TimeEntry::query()
            ->whereBelongsTo($employee)
            ->whereBetween('work_date', [$start, $start->endOfWeek()])
            ->get()
            ->keyBy(fn (TimeEntry $entry): string => "{$entry->project_id}|{$entry->work_date->toDateString()}");
    }

    public function entryFor(int $projectId, string $date): ?TimeEntry
    {
        return $this->weekEntries()->get("{$projectId}|{$date}");
    }

    public function isEditable(?TimeEntry $entry): bool
    {
        return $entry === null || in_array($entry->status, [TimeEntryStatus::Draft, TimeEntryStatus::Rejected], strict: true);
    }

    public function rowTotal(int $projectId): float
    {
        return collect($this->hours[$projectId] ?? [])->sum(fn ($value): float => blank($value) ? 0.0 : (float) $value);
    }

    public function dayTotal(string $date): float
    {
        return collect($this->hours)->sum(fn (array $days): float => blank($days[$date] ?? null) ? 0.0 : (float) $days[$date]);
    }

    public function weekTotal(): float
    {
        return $this->weekDays()->sum(fn (CarbonImmutable $day): float => $this->dayTotal($day->toDateString()));
    }

    public function employee(): ?Employee
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User ? $user->employee : null;
    }

    private function refreshGrid(): void
    {
        $this->cachedWeekEntries = null;
        $this->loadHours();
    }

    private function loadHours(): void
    {
        $this->hours = [];
        $entries = $this->weekEntries();

        foreach ($this->gridProjects() as $project) {
            foreach ($this->weekDays() as $day) {
                $entry = $entries->get("{$project->id}|{$day->toDateString()}");
                $this->hours[$project->id][$day->toDateString()] = $entry === null || (float) $entry->regular_hours == 0.0
                    ? ''
                    : number_format((float) $entry->regular_hours, 2, '.', '');
            }
        }
    }
}
