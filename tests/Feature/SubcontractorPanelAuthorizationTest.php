<?php

use App\Enums\Role;
use App\Filament\Subcontractor\Resources\Contracts\ContractResource;
use App\Filament\Subcontractor\Resources\Contracts\Pages\ListContracts;
use App\Filament\Subcontractor\Resources\Projects\Pages\ListProjects;
use App\Filament\Subcontractor\Resources\Projects\ProjectResource;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('subcontractor');
});

test('only vendor identities can access the subcontractor panel', function () {
    $vendorUser = User::factory()->for(Vendor::factory(), 'vendor')->create();
    $internalUser = userWithRole(Role::ProjectManager);

    expect($vendorUser->canAccessPanel(Filament::getPanel('subcontractor')))->toBeTrue()
        ->and($vendorUser->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($internalUser->canAccessPanel(Filament::getPanel('subcontractor')))->toBeFalse()
        ->and($internalUser->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

test('vendor resources expose only projects and contracts owned by the authenticated vendor', function () {
    $vendor = Vendor::factory()->create();
    $otherVendor = Vendor::factory()->create();
    $vendorUser = User::factory()->for($vendor, 'vendor')->create();
    $visibleProject = Project::factory()->create(['job_number' => 'JOB-VISIBLE']);
    $hiddenProject = Project::factory()->create(['job_number' => 'JOB-HIDDEN']);
    $visibleContract = Contract::factory()->subcontract()->for($visibleProject)->create(['number' => 'SC-VISIBLE', 'vendor_id' => $vendor->getKey()]);
    $hiddenContract = Contract::factory()->subcontract()->for($hiddenProject)->create(['number' => 'SC-HIDDEN', 'vendor_id' => $otherVendor->getKey()]);
    $this->actingAs($vendorUser);

    expect($vendorUser->vendor_id)->toBe($vendor->getKey())
        ->and($visibleContract->vendor_id)->toBe($vendor->getKey())
        ->and(Project::query()->visibleToVendor($vendorUser)->pluck('id')->all())->toContain($visibleProject->getKey())
        ->not->toContain($hiddenProject->getKey());

    Livewire::test(ListProjects::class)
        ->assertCanSeeTableRecords([$visibleProject])
        ->assertCanNotSeeTableRecords([$hiddenProject]);

    Livewire::test(ListContracts::class)
        ->assertCanSeeTableRecords([$visibleContract])
        ->assertCanNotSeeTableRecords([$hiddenContract]);

    $this->get(ProjectResource::getUrl('index'))->assertSuccessful();
    $this->get(ContractResource::getUrl('index'))->assertSuccessful();
});
