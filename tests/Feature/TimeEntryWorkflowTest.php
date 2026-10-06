<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Enums\TimeEntryStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\TimeEntryApprovalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->employeeUser = assignToProject(userWithRole(Role::Employee), $this->project, ProjectRole::Employee);
    $this->employee = Employee::factory()->for($this->employeeUser)->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager);
});

test('an employee can draft and submit own time only on assigned projects', function () {
    $service = app(TimeEntryApprovalService::class);
    $entry = $service->create($this->employee, [
        'project_id' => $this->project->id,
        'work_date' => '2026-07-13',
        'regular_hours' => 8,
        'overtime_hours' => 0,
        'is_billable' => true,
    ], $this->employeeUser);

    expect($entry->status)->toBe(TimeEntryStatus::Draft)
        ->and($service->submit($entry, $this->employeeUser)->status)->toBe(TimeEntryStatus::Submitted);

    $unrelated = Project::factory()->create();
    expect(fn () => $service->create($this->employee, [
        'project_id' => $unrelated->id, 'work_date' => '2026-07-14', 'regular_hours' => 8, 'overtime_hours' => 0,
    ], $this->employeeUser))->toThrow(AuthorizationException::class);
});

test('a manager approves or rejects submitted time and approved entries are locked', function () {
    $service = app(TimeEntryApprovalService::class);
    $entry = TimeEntry::factory()->for($this->employee)->for($this->project)->submitted()->create();

    expect(fn () => $service->reject($entry, $this->manager, ''))->toThrow(ValidationException::class);

    $rejected = $service->reject($entry, $this->manager, 'Please clarify the site activity.');
    expect($rejected->status)->toBe(TimeEntryStatus::Rejected);

    $approved = $service->approve($service->submit($rejected, $this->employeeUser), $this->manager);

    expect($approved->status)->toBe(TimeEntryStatus::Approved)
        ->and($approved->approved_by)->toBe($this->manager->id)
        ->and($this->employeeUser->can('update', $approved))->toBeFalse();
});
