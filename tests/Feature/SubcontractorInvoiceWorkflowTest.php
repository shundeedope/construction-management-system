<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\SubcontractorInvoiceWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->contract = Contract::factory()->subcontract()->for($this->project)->executed()->create(['original_amount' => 500_00]);
    $this->line = ContractLineItem::factory()->for($this->contract)->create(['amount' => 500_00]);
    $this->invoice = subcontractorInvoice($this->project, $this->contract, 'VINV-3001');
    InvoiceLineItem::factory()->for($this->invoice)->for($this->line)->create(['work_completed_this_period' => 100_00]);
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->accounting = userWithRole(Role::Accounting);
});

test('a subcontractor invoice is submitted, approved, locked, and logged idempotently', function () {
    $workflow = app(SubcontractorInvoiceWorkflowService::class);
    $this->actingAs($this->manager);
    $submitted = $workflow->submit($this->invoice, $this->manager);
    $this->actingAs($this->accounting);
    $approved = $workflow->approve($submitted, $this->accounting);
    $again = $workflow->approve($approved, $this->accounting);

    expect($submitted->status)->toBe(InvoiceStatus::Submitted)
        ->and($submitted->subtotal)->toBe(100_00)
        ->and($approved->status)->toBe(InvoiceStatus::Approved)
        ->and($approved->approved_by)->toBe($this->accounting->id)
        ->and($this->accounting->can('update', $approved))->toBeFalse()
        ->and($again->id)->toBe($approved->id)
        ->and(app(ActivityLogger::class)->queryFor($approved)->where('event', 'approved')->count())->toBe(1);
});

test('rejection requires an authorized reviewer and preserves its reason', function () {
    $workflow = app(SubcontractorInvoiceWorkflowService::class);
    $this->actingAs($this->manager);
    $submitted = $workflow->submit($this->invoice, $this->manager);
    $employee = assignToProject(userWithRole(Role::Employee), $this->project, ProjectRole::Employee, canViewFinancials: true);

    expect(fn () => $workflow->approve($submitted, $employee))->toThrow(AuthorizationException::class)
        ->and(fn () => $workflow->reject($submitted, $this->accounting, ''))->toThrow(ValidationException::class);

    $this->actingAs($this->accounting);
    $rejected = $workflow->reject($submitted, $this->accounting, 'Awaiting lien waiver.');

    expect($rejected->status)->toBe(InvoiceStatus::Rejected)
        ->and($rejected->rejection_reason)->toBe('Awaiting lien waiver.')
        ->and(app(ActivityLogger::class)->queryFor($rejected)->where('event', 'rejected')->exists())->toBeTrue();
});
