<?php

use App\Enums\DocumentCategory;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Subcontractor\Resources\Contracts\Pages\ViewContract;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DocumentStorageService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->vendor = Vendor::factory()->create();
    $this->vendorUser = User::factory()->for($this->vendor, 'vendor')->create();
    $this->project = Project::factory()->create();
    $this->contract = Contract::factory()->subcontract()->for($this->project)->executed()->create(['vendor_id' => $this->vendor->id]);
    $this->document = Document::factory()->for($this->project)->create([
        'documentable_type' => Contract::class,
        'documentable_id' => $this->contract->id,
        'category' => DocumentCategory::Contract,
    ]);
});

test('a vendor can upload and download a document for its own contract', function () {
    Filament::setCurrentPanel('subcontractor');
    $storage = app(DocumentStorageService::class);
    $storage->upload($this->document, UploadedFile::fake()->create('ticket.pdf', 20, 'application/pdf'), $this->vendorUser);

    $this->actingAs($this->vendorUser)
        ->get(route('documents.download', $this->document))
        ->assertSuccessful();

    Livewire::test(ViewContract::class, ['record' => $this->contract->id])
        ->assertSeeLivewire(DocumentsRelationManager::class);
});

test('a vendor cannot upload to or download documents belonging to another vendor', function () {
    $otherVendor = Vendor::factory()->create();
    $otherProject = Project::factory()->create();
    $otherContract = Contract::factory()->subcontract()->for($otherProject)->executed()->create(['vendor_id' => $otherVendor->id]);
    $otherDocument = Document::factory()->for($otherProject)->create([
        'documentable_type' => Contract::class,
        'documentable_id' => $otherContract->id,
        'category' => DocumentCategory::Contract,
    ]);

    expect(fn () => app(DocumentStorageService::class)->upload($otherDocument, UploadedFile::fake()->create('attack.pdf', 20, 'application/pdf'), $this->vendorUser))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($this->vendorUser)
        ->get(route('documents.download', $otherDocument))
        ->assertForbidden();
});
