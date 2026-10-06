<?php

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Filament\Subcontractor\Resources\Invoices\Pages\ListInvoices;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\InvoiceLineItem;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ActivityLogger;
use App\Services\SubcontractorInvoiceWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('subcontractor');
    $this->vendor = Vendor::factory()->create();
    $this->vendorUser = User::factory()->for($this->vendor, 'vendor')->create();
    $this->project = Project::factory()->create();
    $this->contract = Contract::factory()->subcontract()->for($this->project)->executed()->create(['vendor_id' => $this->vendor->id, 'original_amount' => 500_00]);
    $this->line = ContractLineItem::factory()->for($this->contract)->create(['amount' => 500_00]);
    $this->invoice = subcontractorInvoice($this->project, $this->contract, 'VINV-PORTAL-1001');
    InvoiceLineItem::factory()->for($this->invoice)->for($this->line)->create(['work_completed_this_period' => 100_00]);
});

test('a vendor can submit a rejected invoice again after correcting it and retains the rejection history', function () {
    $workflow = app(SubcontractorInvoiceWorkflowService::class);
    $this->actingAs($this->vendorUser);
    $submitted = $workflow->submit($this->invoice, $this->vendorUser);

    $accounting = userWithRole(Role::Accounting);
    $rejected = $workflow->reject($submitted, $accounting, 'Provide the delivery ticket.');

    $corrected = $workflow->prepareForResubmission($rejected, $this->vendorUser);
    $resubmitted = $workflow->submit($corrected, $this->vendorUser);

    expect($rejected->rejection_reason)->toBe('Provide the delivery ticket.')
        ->and($corrected->status)->toBe(InvoiceStatus::Draft)
        ->and($resubmitted->status)->toBe(InvoiceStatus::Submitted)
        ->and(app(ActivityLogger::class)->queryFor($resubmitted)->where('event', 'rejected')->exists())->toBeTrue()
        ->and(app(ActivityLogger::class)->queryFor($resubmitted)->where('event', 'corrected_for_resubmission')->exists())->toBeTrue();
});

test('a vendor can see only its own invoices in the portal', function () {
    $otherVendor = Vendor::factory()->create();
    $otherProject = Project::factory()->create();
    $otherContract = Contract::factory()->subcontract()->for($otherProject)->executed()->create(['vendor_id' => $otherVendor->id]);
    $hiddenInvoice = subcontractorInvoice($otherProject, $otherContract, 'VINV-PORTAL-HIDDEN');
    $this->actingAs($this->vendorUser);

    Livewire::test(ListInvoices::class)
        ->assertCanSeeTableRecords([$this->invoice])
        ->assertCanNotSeeTableRecords([$hiddenInvoice]);
});
