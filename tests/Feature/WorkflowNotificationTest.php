<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Models\User;
use App\Services\ChangeOrderWorkflowService;
use App\Services\SubcontractorInvoiceWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);
    $this->contract = Contract::factory()->subcontract()->for($this->project)->executed()->create(['original_amount' => 1_000_00]);
    $this->line = ContractLineItem::factory()->for($this->contract)->create(['amount' => 1_000_00]);
});

test('submitting a subcontractor invoice notifies approvers but not the actor', function () {
    $accounting = userWithRole(Role::Accounting);
    $invoice = Invoice::factory()->subcontractor()->for($this->project)->for($this->contract)->create(['created_by' => $this->manager->id]);
    InvoiceLineItem::factory()->for($invoice)->for($this->line)->create(['work_completed_this_period' => 100_00]);

    app(SubcontractorInvoiceWorkflowService::class)->submit($invoice, $this->manager);

    expect($accounting->notifications()->count())->toBe(1)
        ->and($accounting->notifications()->first()->data['title'])->toContain('awaits approval')
        ->and($this->manager->notifications()->count())->toBe(0);
});

test('approving a subcontractor invoice notifies vendor portal users with a portal link', function () {
    $vendorUser = User::factory()->create(['vendor_id' => $this->contract->vendor_id]);
    $accounting = userWithRole(Role::Accounting);
    $invoice = Invoice::factory()->subcontractor()->for($this->project)->for($this->contract)->create([
        'status' => 'submitted',
        'created_by' => $vendorUser->id,
    ]);
    InvoiceLineItem::factory()->for($invoice)->for($this->line)->create(['work_completed_this_period' => 100_00]);

    app(SubcontractorInvoiceWorkflowService::class)->approve($invoice, $accounting);

    $notification = $vendorUser->notifications()->first();

    expect($vendorUser->notifications()->count())->toBe(1)
        ->and($notification->data['title'])->toContain('approved')
        ->and($notification->data['actions'][0]['url'])->toContain('/subcontractor/');
});

test('rejecting a change order notifies its creator with the reason', function () {
    $admin = userWithRole(Role::Administrator);
    $changeOrder = ChangeOrder::factory()->for($this->project)->for($this->contract)->subcontract()->submitted()->create(['created_by' => $this->manager->id]);

    app(ChangeOrderWorkflowService::class)->reject($changeOrder, $admin, 'Pricing needs revision.');

    $notification = $this->manager->notifications()->first();

    expect($this->manager->notifications()->count())->toBe(1)
        ->and($notification->data['title'])->toContain('rejected')
        ->and($notification->data['body'])->toBe('Pricing needs revision.');
});
