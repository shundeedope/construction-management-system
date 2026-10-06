<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\ClientInvoices\Pages\EditClientInvoice;
use App\Filament\Resources\ClientInvoices\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\PaymentsRelationManager as ProjectPaymentsRelationManager;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->project = Project::factory()->create();
    $this->invoice = Invoice::factory()->for($this->project)->for(Contract::factory()->for($this->project)->create(['client_id' => $this->project->client_id]))->approved()->create([
        'project_id' => $this->project->id,
        'total' => 1_000_00,
    ]);
    $this->accounting = userWithRole(Role::Accounting);
    $this->actingAs($this->accounting);
});

test('accounting can record and remove an invoice payment from payment history', function () {
    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $this->invoice,
        'pageClass' => EditClientInvoice::class,
    ])
        ->callAction(TestAction::make('recordPayment')->table(), [
            'amount' => '400.00',
            'payment_date' => now()->toDateString(),
            'method' => 'wire',
            'reference' => 'WIRE-1001',
        ])
        ->assertNotified();

    $payment = Payment::query()->sole();

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $this->invoice,
        'pageClass' => EditClientInvoice::class,
    ])
        ->assertCanSeeTableRecords([$payment])
        ->callAction(TestAction::make('removePayment')->table($payment))
        ->assertNotified();

    expect($payment->fresh()->trashed())->toBeTrue();
});

test('project payment history is visible only to users with project financial access', function () {
    $payment = Payment::factory()->for($this->project)->for($this->invoice)->create(['project_id' => $this->project->id]);

    Livewire::test(ProjectPaymentsRelationManager::class, [
        'ownerRecord' => $this->project,
        'pageClass' => ViewProject::class,
    ])->assertCanSeeTableRecords([$payment]);

    $manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: false);
    $this->actingAs($manager);

    expect(ProjectPaymentsRelationManager::canViewForRecord($this->project, ViewProject::class))->toBeFalse();
});
