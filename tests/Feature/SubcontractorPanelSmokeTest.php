<?php

use App\Filament\Subcontractor\Resources\ChangeOrders\Pages\ListChangeOrders;
use App\Filament\Subcontractor\Resources\Contracts\Pages\ListContracts;
use App\Filament\Subcontractor\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Subcontractor\Resources\Profile\Pages\EditProfile;
use App\Filament\Subcontractor\Resources\Projects\Pages\ListProjects;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('vendor users can load every current subcontractor panel page', function () {
    Filament::setCurrentPanel('subcontractor');
    $this->actingAs(User::factory()->for(Vendor::factory(), 'vendor')->create());

    foreach ([ListProjects::class, ListContracts::class, ListChangeOrders::class, ListInvoices::class, EditProfile::class] as $component) {
        Livewire::test($component)->assertOk();
    }
});
