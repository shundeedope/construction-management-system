<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\ClientInvoiceWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->contract = Contract::factory()->for($this->project)->executed()->create([
        'client_id' => $this->project->client_id,
        'original_amount' => 500_00,
    ]);
    $this->line = ContractLineItem::factory()->for($this->contract)->create(['amount' => 500_00]);
    $this->invoice = Invoice::factory()->for($this->project)->for($this->contract)->create([
        'project_id' => $this->project->id,
        'contract_id' => $this->contract->id,
        'status' => InvoiceStatus::Draft,
        'subtotal' => 0,
        'retention_amount' => 0,
        'total' => 0,
    ]);
    InvoiceLineItem::factory()->for($this->invoice)->for($this->line)->create(['work_completed_this_period' => 100_00]);
    $this->accounting = userWithRole(Role::Accounting);
});

test('a client invoice is approved, sent, locked, and voided with preserved history', function () {
    $workflow = app(ClientInvoiceWorkflowService::class);
    $this->actingAs($this->accounting);

    $approved = $workflow->approve($this->invoice, $this->accounting);
    $sent = $workflow->send($approved, $this->accounting);
    $voided = $workflow->void($sent, $this->accounting, 'Corrected client entity.');

    expect($approved->status)->toBe(InvoiceStatus::Approved)
        ->and($approved->approved_at)->not->toBeNull()
        ->and($approved->subtotal)->toBe(100_00)
        ->and($sent->status)->toBe(InvoiceStatus::Sent)
        ->and($sent->sent_at)->not->toBeNull()
        ->and($voided->status)->toBe(InvoiceStatus::Void)
        ->and($voided->voided_at)->not->toBeNull()
        ->and($voided->void_reason)->toBe('Corrected client entity.')
        ->and(app(ActivityLogger::class)->queryFor($voided)->where('event', 'voided')->exists())->toBeTrue();
});

test('only authorized users can approve and void reasons are required', function () {
    $employee = assignToProject(userWithRole(Role::Employee), $this->project, ProjectRole::Employee, canViewFinancials: true);
    $workflow = app(ClientInvoiceWorkflowService::class);

    expect(fn () => $workflow->approve($this->invoice, $employee))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $workflow->void($this->invoice, $this->accounting, ''))
        ->toThrow(ValidationException::class);
});
