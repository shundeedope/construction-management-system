<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Enums\TimeEntryStatus;
use App\Filament\Pages\WeeklyTimesheet;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->project = Project::factory()->create();
    $this->user = assignToProject(userWithRole(Role::SiteSupervisor), $this->project, ProjectRole::SiteSupervisor);
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

test('typing hours into the grid and saving creates draft time entries', function () {
    $monday = now()->startOfWeek()->toDateString();

    Livewire::test(WeeklyTimesheet::class)
        ->set("hours.{$this->project->id}.{$monday}", '7.5')
        ->callAction('saveDraft');

    $entry = TimeEntry::query()->whereBelongsTo($this->employee)->firstOrFail();

    expect((float) $entry->regular_hours)->toBe(7.5)
        ->and($entry->status)->toBe(TimeEntryStatus::Draft)
        ->and($entry->work_date->toDateString())->toBe($monday);
});

test('submitting the week sends draft entries for approval and locks them in the grid', function () {
    $monday = now()->startOfWeek()->toDateString();

    Livewire::test(WeeklyTimesheet::class)
        ->set("hours.{$this->project->id}.{$monday}", '8')
        ->callAction('submitWeek')
        ->assertNotified();

    $entry = TimeEntry::query()->whereBelongsTo($this->employee)->firstOrFail();

    expect($entry->status)->toBe(TimeEntryStatus::Submitted);

    Livewire::test(WeeklyTimesheet::class)
        ->assertSee('Submitted');
});

test('approved hours render read-only and clearing a draft deletes it', function () {
    $monday = now()->startOfWeek()->toDateString();
    TimeEntry::factory()->for($this->employee)->for($this->project)->create([
        'work_date' => $monday,
        'regular_hours' => 8,
        'status' => TimeEntryStatus::Approved,
    ]);
    $tuesday = now()->startOfWeek()->addDay()->toDateString();
    TimeEntry::factory()->for($this->employee)->for($this->project)->create([
        'work_date' => $tuesday,
        'regular_hours' => 4,
        'status' => TimeEntryStatus::Draft,
    ]);

    Livewire::test(WeeklyTimesheet::class)
        ->assertSee('Approved')
        ->set("hours.{$this->project->id}.{$monday}", '2')
        ->set("hours.{$this->project->id}.{$tuesday}", '')
        ->callAction('saveDraft');

    expect((float) TimeEntry::query()->whereDate('work_date', $monday)->firstOrFail()->regular_hours)->toBe(8.0)
        ->and(TimeEntry::query()->whereDate('work_date', $tuesday)->withoutTrashed()->exists())->toBeFalse();
});
