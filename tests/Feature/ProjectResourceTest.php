<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\ChangeOrders\ChangeOrderResource;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\RelationManagers\UsersRelationManager;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\NumberSequencesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\AttachAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, NumberSequencesSeeder::class]);
    Filament::setCurrentPanel('admin');
});

test('a project manager can create projects with immutable generated job numbers', function () {
    $manager = userWithRole(Role::ProjectManager);
    $client = Client::factory()->create();
    $this->actingAs($manager);

    Livewire::test(CreateProject::class)
        ->fillForm([
            'name' => 'Riverside Offices',
            'client_id' => $client->getKey(),
            'status' => 'planning',
            'country' => 'US',
            'start_date' => '2026-07-01',
            'expected_completion_date' => '2026-12-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $project = Project::query()->where('name', 'Riverside Offices')->firstOrFail();

    expect($project->job_number)->toBe('JOB-2026-0001');
    expect($manager->fresh()->isAssignedTo($project))->toBeTrue();

    Livewire::test(EditProject::class, ['record' => $project->getKey()])
        ->fillForm(['job_number' => 'HACKED-0001', 'name' => 'Riverside Office Campus'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($project->fresh()->job_number)->toBe('JOB-2026-0001')
        ->and($project->fresh()->name)->toBe('Riverside Office Campus');
});

test('project tables are scoped and project teams can be managed only by authorized users', function () {
    $project = Project::factory()->create(['name' => 'Assigned project']);
    $otherProject = Project::factory()->create(['name' => 'Other project']);
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager);
    $teamMember = User::factory()->create();
    $this->actingAs($manager);

    Livewire::test(ListProjects::class)
        ->assertCanSeeTableRecords([$project])
        ->assertCanNotSeeTableRecords([$otherProject])
        ->searchTable('Assigned')
        ->assertCanSeeTableRecords([$project])
        ->filterTable('status', 'planning')
        ->assertCanSeeTableRecords([$project]);

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => ViewProject::class,
    ])
        ->callAction(TestAction::make(AttachAction::class)->table(), [
            'recordId' => $teamMember->getKey(),
            'project_role' => ProjectRole::Employee->value,
            'can_view_financials' => false,
            'can_manage_documents' => true,
        ])
        ->assertNotified();

    expect($project->users()->whereKey($teamMember)->firstOrFail()->pivot->can_manage_documents)->toBeTrue();

    $this->get(ProjectResource::getUrl('view', ['record' => $otherProject]))->assertNotFound();
});

test('project validation rejects an invalid completion schedule', function () {
    $client = Client::factory()->create();
    $this->actingAs(userWithRole(Role::ProjectManager));

    Livewire::test(CreateProject::class)
        ->fillForm([
            'name' => 'Invalid schedule',
            'client_id' => $client->getKey(),
            'status' => 'planning',
            'country' => 'US',
            'start_date' => '2026-12-31',
            'expected_completion_date' => '2026-01-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['expected_completion_date']);
});

test('the project create page renders as a three-step wizard', function () {
    $this->actingAs(userWithRole(Role::ProjectManager));

    Livewire::test(CreateProject::class)
        ->assertOk()
        ->assertSee('Details')
        ->assertSee('Schedule & site')
        ->assertSee('Notes');
});

test('the change-order form pre-selects the project passed by a quick action', function () {
    $project = Project::factory()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->actingAs($manager);

    $this->withoutExceptionHandling();
    $this->get(ChangeOrderResource::getUrl('create').'?project_id='.$project->getKey())
        ->assertOk()
        ->assertSee($project->job_number);
});
