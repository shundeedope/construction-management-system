<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ContractStatus;
use App\Enums\DocumentCategory;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->project = Project::factory()->create();
    $this->otherProject = Project::factory()->create();
});

it('hides an unassigned project from an employee', function () {
    $employee = userWithRole(Role::Employee);

    expect($employee->can('view', $this->project))->toBeFalse();

    $employee = assignToProject($employee, $this->project);

    expect($employee->can('view', $this->project))->toBeTrue()
        ->and($employee->can('view', $this->otherProject))->toBeFalse();
});

it('scopes project queries to the same boundary as the view policy', function () {
    $employee = assignToProject(userWithRole(Role::Employee), $this->project);
    $executive = userWithRole(Role::Executive);

    $visibleToEmployee = Project::query()->visibleTo($employee)->pluck('id');
    $visibleToExecutive = Project::query()->visibleTo($executive)->pluck('id');

    expect($visibleToEmployee->all())->toBe([$this->project->id])
        ->and($visibleToExecutive)->toHaveCount(2);
});

it('lets a manager reach the financials of an assigned project', function () {
    $manager = assignToProject(
        userWithRole(Role::ProjectManager),
        $this->project,
        ProjectRole::ProjectManager,
        canViewFinancials: true,
    );

    $contract = Contract::factory()->for($this->project)->create();

    expect($manager->can('view', $this->project))->toBeTrue()
        ->and($manager->can('update', $this->project))->toBeTrue()
        ->and($manager->can('viewFinancials', $this->project))->toBeTrue()
        ->and($manager->can('view', $contract))->toBeTrue()
        ->and($manager->can('update', $contract))->toBeTrue();
});

it('denies a manager the financials of a project they are not assigned to', function () {
    $manager = assignToProject(
        userWithRole(Role::ProjectManager),
        $this->project,
        ProjectRole::ProjectManager,
        canViewFinancials: true,
    );

    $foreignContract = Contract::factory()->for($this->otherProject)->create();

    expect($manager->can('viewFinancials', $this->otherProject))->toBeFalse()
        ->and($manager->can('view', $foreignContract))->toBeFalse()
        ->and($manager->can('update', $this->otherProject))->toBeFalse();
});

it('withholds financials from a supervisor without the assignment flag', function () {
    $supervisor = assignToProject(
        userWithRole(Role::SiteSupervisor),
        $this->project,
        ProjectRole::SiteSupervisor,
        canViewFinancials: false,
    );

    $contract = Contract::factory()->for($this->project)->create();
    $invoice = Invoice::factory()->for($this->project)->for($contract)->create();

    expect($supervisor->can('view', $this->project))->toBeTrue()
        ->and($supervisor->can('viewFinancials', $this->project))->toBeFalse()
        ->and($supervisor->can('view', $contract))->toBeFalse()
        ->and($supervisor->can('view', $invoice))->toBeFalse();
});

it('grants a supervisor financials once the assignment flag is set', function () {
    $supervisor = assignToProject(
        userWithRole(Role::SiteSupervisor),
        $this->project,
        ProjectRole::SiteSupervisor,
        canViewFinancials: true,
    );

    $contract = Contract::factory()->for($this->project)->create();

    expect($supervisor->can('viewFinancials', $this->project))->toBeTrue()
        ->and($supervisor->can('view', $contract))->toBeTrue()
        // Viewing is not managing: the supervisor still holds no contract capability.
        ->and($supervisor->can('update', $contract))->toBeFalse();
});

it('gives accounting every project financial without a project assignment', function () {
    $accounting = userWithRole(Role::Accounting);

    $contract = Contract::factory()->for($this->otherProject)->create();
    $invoice = Invoice::factory()->for($this->otherProject)->for($contract)->create();
    $payment = Payment::factory()->for($this->otherProject)->for($invoice)->create();

    expect($accounting->can('view', $this->otherProject))->toBeTrue()
        ->and($accounting->can('viewFinancials', $this->otherProject))->toBeTrue()
        ->and($accounting->can('view', $contract))->toBeTrue()
        ->and($accounting->can('view', $invoice))->toBeTrue()
        ->and($accounting->can('create', Payment::class))->toBeTrue()
        ->and($accounting->can('delete', $payment))->toBeTrue()
        // No operational project editing, even though every project is visible.
        ->and($accounting->can('update', $this->otherProject))->toBeFalse()
        ->and($accounting->can('manageTeam', $this->otherProject))->toBeFalse();
});

it('lets a read-only executive see financials but change nothing', function () {
    $executive = userWithRole(Role::Executive);

    $contract = Contract::factory()->for($this->project)->create();
    $invoice = Invoice::factory()->for($this->project)->for($contract)->create(['status' => InvoiceStatus::Approved]);
    $changeOrder = ChangeOrder::factory()->for($this->project)->for($contract)->create(['status' => ChangeOrderStatus::Submitted]);

    expect($executive->can('view', $this->project))->toBeTrue()
        ->and($executive->can('viewFinancials', $this->project))->toBeTrue()
        ->and($executive->can('view', $contract))->toBeTrue();

    expect($executive->can('update', $this->project))->toBeFalse()
        ->and($executive->can('create', Contract::class))->toBeFalse()
        ->and($executive->can('update', $contract))->toBeFalse()
        ->and($executive->can('execute', $contract))->toBeFalse()
        ->and($executive->can('approve', $changeOrder))->toBeFalse()
        ->and($executive->can('approve', $invoice))->toBeFalse()
        ->and($executive->can('void', $invoice))->toBeFalse()
        ->and($executive->can('create', Payment::class))->toBeFalse();
});

it('gives the administrator full access everywhere without an assignment', function () {
    $admin = userWithRole(Role::Administrator);

    $contract = Contract::factory()->for($this->otherProject)->create(['status' => ContractStatus::PendingApproval]);

    expect($admin->can('view', $this->otherProject))->toBeTrue()
        ->and($admin->can('update', $this->otherProject))->toBeTrue()
        ->and($admin->can('viewFinancials', $this->otherProject))->toBeTrue()
        ->and($admin->can('execute', $contract))->toBeTrue()
        ->and($admin->can('manageTeam', $this->otherProject))->toBeTrue();
});

it('locks executed contracts against editing by anybody', function () {
    $manager = assignToProject(
        userWithRole(Role::ProjectManager),
        $this->project,
        ProjectRole::ProjectManager,
        canViewFinancials: true,
    );

    $draft = Contract::factory()->for($this->project)->create(['status' => ContractStatus::Draft]);
    $executed = Contract::factory()->for($this->project)->create(['status' => ContractStatus::Executed]);

    expect($manager->can('update', $draft))->toBeTrue()
        ->and($manager->can('delete', $draft))->toBeTrue()
        ->and($manager->can('update', $executed))->toBeFalse()
        ->and($manager->can('delete', $executed))->toBeFalse()
        ->and($manager->can('execute', $executed))->toBeFalse();
});

it('gates financial documents behind financial access, not merely project access', function () {
    $supervisor = assignToProject(
        userWithRole(Role::SiteSupervisor),
        $this->project,
        ProjectRole::SiteSupervisor,
        canViewFinancials: false,
        canManageDocuments: true,
    );

    $plan = Document::factory()->for($this->project)->create(['category' => DocumentCategory::Plan]);
    $contractDocument = Document::factory()->for($this->project)->create(['category' => DocumentCategory::Contract]);

    expect($supervisor->can('view', $plan))->toBeTrue()
        ->and($supervisor->can('download', $plan))->toBeTrue()
        ->and($supervisor->can('update', $plan))->toBeTrue()
        ->and($supervisor->can('view', $contractDocument))->toBeFalse()
        ->and($supervisor->can('download', $contractDocument))->toBeFalse();
});

it('denies document management without the assignment flag', function () {
    $employee = assignToProject(
        userWithRole(Role::Employee),
        $this->project,
        ProjectRole::Employee,
        canManageDocuments: false,
    );

    $plan = Document::factory()->for($this->project)->create(['category' => DocumentCategory::Plan]);

    expect($employee->can('view', $plan))->toBeTrue()
        ->and($employee->can('update', $plan))->toBeFalse()
        ->and($employee->can('delete', $plan))->toBeFalse();
});

it('denies every document on an unassigned project', function () {
    $employee = assignToProject(userWithRole(Role::Employee), $this->project, canManageDocuments: true);

    $foreignDocument = Document::factory()->for($this->otherProject)->create(['category' => DocumentCategory::Plan]);

    expect($employee->can('view', $foreignDocument))->toBeFalse()
        ->and($employee->can('update', $foreignDocument))->toBeFalse();
});
