<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Filament\Subcontractor\Resources\ChangeOrders\ChangeOrderResource;
use App\Filament\Subcontractor\Resources\Contracts\ContractResource;
use App\Filament\Subcontractor\Resources\Contracts\Pages\ViewContract;
use App\Filament\Subcontractor\Resources\Invoices\InvoiceResource;
use App\Filament\Subcontractor\Resources\Projects\ProjectResource;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('subcontractor');
    $this->vendor = Vendor::factory()->create();
    $this->vendorUser = User::factory()->for($this->vendor, 'vendor')->create();
    $this->project = Project::factory()->create();
    $this->contract = Contract::factory()->subcontract()->for($this->project)->executed()->create(['vendor_id' => $this->vendor->id]);
});

test('portal queries never expose owner contracts, client invoices, internal margin, or another vendor', function () {
    $ownerContract = Contract::factory()->for($this->project)->executed()->create();
    $clientInvoice = Invoice::factory()->for($this->project)->for($ownerContract)->create();
    $otherVendor = Vendor::factory()->create();
    $otherContract = Contract::factory()->subcontract()->for($this->project)->executed()->create(['vendor_id' => $otherVendor->id]);
    $otherInvoice = subcontractorInvoice($this->project, $otherContract, 'VINV-OTHER-VENDOR');
    $approvedChange = ChangeOrder::factory()->for($this->project)->for($this->contract)->create(['type' => ChangeOrderType::Subcontract, 'status' => ChangeOrderStatus::Approved]);
    $hiddenOwnerChange = ChangeOrder::factory()->for($this->project)->for($ownerContract)->create(['type' => ChangeOrderType::Owner, 'status' => ChangeOrderStatus::Approved]);
    $this->actingAs($this->vendorUser);

    expect(ContractResource::getEloquentQuery()->pluck('id')->all())->toContain($this->contract->id)->not->toContain($ownerContract->id, $otherContract->id)
        ->and(InvoiceResource::getEloquentQuery()->pluck('id')->all())->not->toContain($clientInvoice->id, $otherInvoice->id)
        ->and(ChangeOrderResource::getEloquentQuery()->pluck('id')->all())->toContain($approvedChange->id)->not->toContain($hiddenOwnerChange->id);

    $this->get(ContractResource::getUrl('view', ['record' => $ownerContract]))->assertNotFound();
    $this->get(InvoiceResource::getUrl('edit', ['record' => $otherInvoice]))->assertNotFound();
    expect(fn () => Livewire::test(ViewContract::class, ['record' => $ownerContract->id]))->toThrow(ModelNotFoundException::class);
    $this->get(ProjectResource::getUrl('index'))
        ->assertSuccessful()
        ->assertDontSee('margin', false)
        ->assertDontSee($ownerContract->number)
        ->assertDontSee($clientInvoice->number);
});
