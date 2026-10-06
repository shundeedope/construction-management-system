<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Filament\Resources\ChangeOrders\Pages\EditChangeOrder;
use App\Filament\Resources\ClientInvoices\Pages\EditClientInvoice;
use App\Filament\Resources\OwnerContracts\Pages\ViewOwnerContract;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\SubcontractorInvoices\Pages\EditSubcontractorInvoice;
use App\Filament\Resources\Subcontracts\Pages\ViewSubcontract;
use App\Models\ChangeOrder;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->project = Project::factory()->create();
    $this->manager = assignToProject(userWithRole(Role::ProjectManager), $this->project, ProjectRole::ProjectManager, canViewFinancials: true, canManageDocuments: true);
    $this->actingAs($this->manager);
    $this->contract = Contract::factory()->for($this->project)->executed()->create(['client_id' => $this->project->client_id]);
});

test('documents relation manager loads for project and financial records', function () {
    $changeOrder = ChangeOrder::factory()->for($this->project)->for($this->contract)->create();
    $clientInvoice = Invoice::factory()->for($this->project)->for($this->contract)->create(['project_id' => $this->project->id]);
    $subcontractorInvoice = Invoice::factory()->subcontractor()->for($this->project)->for($this->contract)->create(['project_id' => $this->project->id]);

    foreach ([
        [$this->project, ViewProject::class],
        [$this->contract, ViewOwnerContract::class],
        [$this->contract, ViewSubcontract::class],
        [$changeOrder, EditChangeOrder::class],
        [$clientInvoice, EditClientInvoice::class],
        [$subcontractorInvoice, EditSubcontractorInvoice::class],
    ] as [$ownerRecord, $pageClass]) {
        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $ownerRecord, 'pageClass' => $pageClass])->assertOk();
    }
});
