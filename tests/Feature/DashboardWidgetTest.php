<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Widgets\ApprovalsInbox;
use App\Filament\Widgets\AttentionOverview;
use App\Filament\Widgets\CashFlowChart;
use App\Filament\Widgets\FinancialOverview;
use App\Filament\Widgets\ProjectsByStatus;
use App\Models\ChangeOrder;
use App\Models\ComplianceRecord;
use App\Models\ComplianceRequirement;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\DashboardMetricsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('financial widget reconciles approved non-void financial records', function () {
    $project = Project::factory()->active()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $ownerContract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed, 'original_amount' => 1_000_00]);
    $subcontract = Contract::factory()->subcontract()->for($project)->create(['status' => ContractStatus::Executed, 'original_amount' => 400_00]);
    $approvedChange = ChangeOrder::factory()->for($project)->for($ownerContract)->create(['status' => ChangeOrderStatus::Approved]);
    $approvedChange->lineItems()->create(['description' => 'Approved owner change', 'amount' => 100_00, 'sort_order' => 1]);
    Invoice::factory()->for($project)->for($ownerContract)->create(['status' => InvoiceStatus::Approved, 'total' => 500_00]);
    Invoice::factory()->subcontractor()->for($project)->for($subcontract)->create(['status' => InvoiceStatus::Sent, 'total' => 250_00]);
    Payment::factory()->for($project)->for(Invoice::query()->first())->create(['amount' => 100_00]);
    $voidInvoice = Invoice::factory()->for($project)->for($ownerContract)->create(['status' => InvoiceStatus::Void, 'total' => 900_00]);
    Payment::factory()->for($project)->for($voidInvoice)->create(['amount' => 200_00]);
    $this->actingAs($manager);

    Livewire::test(FinancialOverview::class)
        ->assertOk()
        ->assertSee('Active projects')
        ->assertSee('$1,100.00')
        ->assertSee('$400.00')
        ->assertSee('$250.00')
        ->assertSee('$700.00');
});

test('attention and project status widgets show only visible workflow data', function () {
    $project = Project::factory()->active()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed]);
    Invoice::factory()->for($project)->for($contract)->create(['status' => InvoiceStatus::Sent, 'due_date' => today()->subDay(), 'total' => 100_00]);
    ChangeOrder::factory()->for($project)->for($contract)->create(['status' => ChangeOrderStatus::Submitted]);
    TimeEntry::factory()->for($project)->submitted()->create();
    $this->actingAs($manager);

    Livewire::test(AttentionOverview::class)
        ->assertOk()
        ->assertSee('Overdue invoices')
        ->assertSee('Pending changes')
        ->assertSee('Pending timesheets');

    Livewire::test(ProjectsByStatus::class)
        ->assertOk()
        ->assertSee('Projects By Status');
});

test('attention metrics reconcile each operational queue for an assigned manager', function () {
    $project = Project::factory()->active()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed]);
    Invoice::factory()->for($project)->for($contract)->create(['status' => InvoiceStatus::Sent, 'due_date' => today()->subDay()]);
    ChangeOrder::factory()->for($project)->for($contract)->submitted()->create();
    TimeEntry::factory()->for($project)->submitted()->create();
    $requirement = ComplianceRequirement::factory()->create(['applies_to' => 'project']);
    ComplianceRecord::factory()->for($requirement, 'requirement')->for($project)->expiringSoon()->create(['employee_id' => null]);
    $this->actingAs($manager);

    expect(app(DashboardMetricsService::class)->attention($manager))->toBe([
        'overdue_invoices' => 1,
        'pending_changes' => 1,
        'pending_timesheets' => 1,
        'expiring_compliance' => 1,
    ]);

    Livewire::test(AttentionOverview::class)
        ->assertSee('1')
        ->assertSee('Expiring compliance');
});

test('the approvals inbox lists submitted change orders and approves them inline', function () {
    $project = Project::factory()->active()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed]);
    $submitted = ChangeOrder::factory()->for($project)->for($contract)->submitted()->create();
    $draft = ChangeOrder::factory()->for($project)->for($contract)->create();
    $this->actingAs($manager);

    Livewire::test(ApprovalsInbox::class)
        ->assertCanSeeTableRecords([$submitted])
        ->assertCanNotSeeTableRecords([$draft])
        ->callAction(TestAction::make('approve')->table($submitted))
        ->assertNotified();

    expect($submitted->fresh()->status)->toBe(ChangeOrderStatus::Approved);
});

test('the cash flow chart renders for a financially scoped manager', function () {
    $project = Project::factory()->active()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->create(['client_id' => $project->client_id, 'status' => ContractStatus::Executed]);
    $invoice = Invoice::factory()->for($project)->for($contract)->create(['status' => InvoiceStatus::Sent]);
    Payment::factory()->for($project)->for($invoice)->create(['amount' => 100_00, 'payment_date' => now()->subMonth()]);
    $this->actingAs($manager);

    Livewire::test(CashFlowChart::class)
        ->assertOk()
        ->assertSee('Cash flow');
});

test('attention stats deep-link to the matching filtered lists', function () {
    $project = Project::factory()->active()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->actingAs($manager);

    Livewire::test(AttentionOverview::class)
        ->assertOk()
        ->assertSeeHtml('tab=overdue')
        ->assertSeeHtml('tab=awaiting_approval');
});
