<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\ChangeOrder;
use App\Models\ChangeOrderLineItem;
use App\Models\Contract;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\ChangeOrderWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->contract = Contract::factory()->for($this->project)->executed()->create(['client_id' => $this->project->client_id]);
    $this->changeOrder = ChangeOrder::factory()->for($this->project)->for($this->contract)->create();
    ChangeOrderLineItem::factory()->for($this->changeOrder)->create(['amount' => 25_000]);
});

test('a draft can be submitted and an approved change order is idempotent and locked', function () {
    $workflow = app(ChangeOrderWorkflowService::class);

    $this->actingAs($this->manager);
    $submitted = $workflow->submit($this->changeOrder, $this->manager);
    $approved = $workflow->approve($submitted, $this->manager);
    $again = $workflow->approve($approved, $this->manager);
    $lineItem = $submitted->lineItems()->firstOrFail();

    expect($this->manager->can('update', $this->changeOrder))->toBeTrue()
        ->and($submitted->status)->toBe(ChangeOrderStatus::Submitted)
        ->and($submitted->submitted_at)->not->toBeNull()
        ->and($this->manager->can('update', $lineItem))->toBeFalse()
        ->and($approved->status)->toBe(ChangeOrderStatus::Approved)
        ->and($approved->approved_at)->not->toBeNull()
        ->and($approved->approved_by)->toBe($this->manager->id)
        ->and($again->id)->toBe($approved->id)
        ->and($this->manager->can('update', $approved))->toBeFalse()
        ->and(app(ActivityLogger::class)->queryFor($approved)->where('event', 'approved')->count())->toBe(1);
});

test('approval requires a privileged project member', function () {
    $submitted = ChangeOrder::factory()->for($this->project)->for($this->contract)->submitted()->create();
    $employee = assignToProject(userWithRole(Role::Employee), $this->project, canViewFinancials: true);

    expect(fn () => app(ChangeOrderWorkflowService::class)->approve($submitted, $employee))
        ->toThrow(AuthorizationException::class);

    $approved = ChangeOrder::factory()->for($this->project)->for($this->contract)->approved()->create();

    expect(fn () => app(ChangeOrderWorkflowService::class)->approve($approved, $employee))
        ->toThrow(AuthorizationException::class);
});

test('rejection requires and retains a reason without approving the change order', function () {
    $workflow = app(ChangeOrderWorkflowService::class);
    $submitted = $workflow->submit($this->changeOrder, $this->manager);

    expect(fn () => $workflow->reject($submitted, $this->manager, ''))
        ->toThrow(ValidationException::class);

    $rejected = $workflow->reject($submitted, $this->manager, 'Supporting pricing is required.');

    expect($rejected->status)->toBe(ChangeOrderStatus::Rejected)
        ->and($rejected->rejected_at)->not->toBeNull()
        ->and($rejected->rejection_reason)->toBe('Supporting pricing is required.')
        ->and($rejected->approved_at)->toBeNull()
        ->and($rejected->approved_by)->toBeNull();
});
