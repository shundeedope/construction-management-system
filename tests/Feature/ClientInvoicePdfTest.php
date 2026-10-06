<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Services\CompanySettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->contract = Contract::factory()->for($this->project)->executed()->create([
        'client_id' => $this->project->client_id,
        'original_amount' => 1_000_00,
    ]);
    $this->contractLine = ContractLineItem::factory()->for($this->contract)->create([
        'description' => 'Foundation progress',
        'amount' => 1_000_00,
    ]);
    $this->invoice = Invoice::factory()->for($this->project)->for($this->contract)->approved()->create([
        'number' => 'INV PDF 1001',
        'project_id' => $this->project->id,
        'contract_id' => $this->contract->id,
        'subtotal' => 100_00,
        'retention_amount' => 500,
        'total' => 95_00,
        'notes' => 'Thank you for your continued partnership.',
    ]);
    InvoiceLineItem::factory()->for($this->invoice)->for($this->contractLine)->create([
        'scheduled_value' => 1_000_00,
        'previously_billed' => 0,
        'work_completed_this_period' => 100_00,
        'materials_stored' => 0,
        'retention_amount' => 500,
        'current_due' => 95_00,
    ]);
    CompanySetting::query()->updateOrCreate(
        ['key' => 'invoice_instructions'],
        ['value' => 'Please pay by ACH within 30 days.', 'type' => 'string'],
    );
    app(CompanySettings::class)->flush();
});

test('an authorized user receives a reconciled client invoice PDF download', function () {
    $manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true);

    $response = $this->actingAs($manager)->get(route('client-invoices.pdf', $this->invoice));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename=client-invoice-inv-pdf-1001.pdf');

    expect($response->getContent())->toStartWith('%PDF')
        ->and($response->getContent())->toContain('Foundation progress')
        ->and($response->getContent())->toContain('Please pay by ACH within 30 days.')
        ->and($response->getContent())->toContain('95.00');
});

test('users outside the financial project scope cannot download a client invoice PDF', function () {
    $employee = userWithRole(Role::Employee);

    $this->actingAs($employee)
        ->get(route('client-invoices.pdf', $this->invoice))
        ->assertForbidden();
});

test('the pdf endpoint streams inline when requested for the preview modal', function () {
    $project = Project::factory()->create();
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id]);
    $invoice = Invoice::factory()->for($project)->for($contract)->create();
    $this->actingAs($manager);

    $this->get(route('client-invoices.pdf', ['invoice' => $invoice, 'inline' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=client-invoice-'.Str::slug($invoice->number).'.pdf');

    $this->get(route('client-invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=client-invoice-'.Str::slug($invoice->number).'.pdf');
});
