<?php

use App\Enums\Role;
use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Vendor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole(Role::Administrator));
});

test('an authorized user can list, search, create, and edit vendors', function () {
    $vendor = Vendor::factory()->create(['company_name' => 'Cedar Electrical']);
    $otherVendor = Vendor::factory()->create();

    Livewire::test(ListVendors::class)
        ->assertOk()
        ->searchTable('Cedar')
        ->assertCanSeeTableRecords([$vendor])
        ->assertCanNotSeeTableRecords([$otherVendor]);

    Livewire::test(CreateVendor::class)
        ->fillForm([
            'company_name' => 'Summit Steel',
            'country' => 'US',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $createdVendor = Vendor::query()->where('company_name', 'Summit Steel')->firstOrFail();

    Livewire::test(EditVendor::class, ['record' => $createdVendor->getKey()])
        ->fillForm(['company_name' => 'Summit Steel Works'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($createdVendor->fresh()->company_name)->toBe('Summit Steel Works');
});

test('vendor validation and insurance warnings work', function () {
    $expiredVendor = Vendor::factory()->expiredInsurance()->create();

    Livewire::test(CreateVendor::class)
        ->fillForm(['company_name' => null, 'country' => 'USA'])
        ->call('create')
        ->assertHasFormErrors(['company_name' => 'required', 'country' => 'size']);

    Livewire::test(ListVendors::class)
        ->filterTable('insurance_expiring')
        ->assertCanSeeTableRecords([$expiredVendor]);
});

test('organization resources are forbidden to users without organization access', function () {
    $this->actingAs(userWithRole(Role::Employee));

    $this->get(VendorResource::getUrl())->assertForbidden();
});
