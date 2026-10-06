<?php

use App\Enums\Role;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\RelationManagers\ContactsRelationManager;
use App\Models\Client;
use App\Models\ClientContact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole(Role::Administrator));
});

test('an authorized user can list, search, create, and edit clients', function () {
    $client = Client::factory()->create(['company_name' => 'Northstar Development']);
    $otherClient = Client::factory()->create();

    Livewire::test(ListClients::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$client, $otherClient])
        ->searchTable('Northstar')
        ->assertCanSeeTableRecords([$client])
        ->assertCanNotSeeTableRecords([$otherClient]);

    Livewire::test(CreateClient::class)
        ->fillForm([
            'company_name' => 'Harbor Properties',
            'email' => 'accounts@harbor.test',
            'country' => 'US',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $createdClient = Client::query()->where('company_name', 'Harbor Properties')->firstOrFail();

    Livewire::test(EditClient::class, ['record' => $createdClient->getKey()])
        ->fillForm(['company_name' => 'Harbor Property Group'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($createdClient->fresh()->company_name)->toBe('Harbor Property Group');
});

test('client validation and contacts are managed through the resource', function () {
    $client = Client::factory()->create();

    Livewire::test(CreateClient::class)
        ->fillForm(['company_name' => null, 'country' => 'USA'])
        ->call('create')
        ->assertHasFormErrors(['company_name' => 'required', 'country' => 'size']);

    Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $client,
        'pageClass' => EditClient::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'is_primary' => true,
        ])
        ->assertNotified();

    expect(ClientContact::query()->whereBelongsTo($client)->where('email', 'ada@example.test')->exists())->toBeTrue();
});

test('organization resources are forbidden to users without organization access', function () {
    $this->actingAs(userWithRole(Role::Employee));

    $this->get(ClientResource::getUrl())->assertForbidden();
});
