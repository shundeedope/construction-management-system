<?php

use App\Enums\ContractStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\ContractWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
});

test('a contract follows its authorized workflow and locks its Schedule of Values at execution', function () {
    $contract = Contract::factory()->for($this->project)->create(['status' => ContractStatus::Draft, 'client_id' => $this->project->client_id]);
    ContractLineItem::factory()->for($contract)->create(['amount' => 50_001]);

    $workflow = app(ContractWorkflowService::class);
    $pending = $workflow->submitForApproval($contract, $this->manager);
    $executed = $workflow->execute($pending, $this->manager);

    expect($pending->status)->toBe(ContractStatus::PendingApproval)
        ->and($executed->status)->toBe(ContractStatus::Executed)
        ->and($executed->original_amount)->toBe(50_001)
        ->and($executed->executed_at)->not->toBeNull()
        ->and($this->manager->can('update', $executed))->toBeFalse();

    $again = $workflow->execute($executed, $this->manager);

    expect($again->id)->toBe($executed->id)
        ->and(app(ActivityLogger::class)->queryFor($executed)->where('event', 'executed')->count())->toBe(1);
});

test('invalid transitions and unauthorized execution are rejected', function () {
    $draft = Contract::factory()->for($this->project)->create(['client_id' => $this->project->client_id]);

    expect(fn () => app(ContractWorkflowService::class)->execute($draft, $this->manager))
        ->toThrow(LogicException::class);

    $pending = Contract::factory()->for($this->project)->create(['client_id' => $this->project->client_id, 'status' => ContractStatus::PendingApproval]);
    $employee = assignToProject(userWithRole(Role::Employee), $this->project, canViewFinancials: true);

    expect(fn () => app(ContractWorkflowService::class)->execute($pending, $employee))
        ->toThrow(AuthorizationException::class);
});
