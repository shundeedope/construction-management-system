<?php

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Livewire\GlobalSearch;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('global search finds projects by job number and invoices by number', function () {
    $project = Project::factory()->create(['job_number' => 'JOB-2026-031', 'name' => 'Riverside Plaza']);
    $manager = assignToProject(userWithRole(Role::ProjectManager), $project, ProjectRole::ProjectManager, canViewFinancials: true);
    $contract = Contract::factory()->for($project)->executed()->create(['client_id' => $project->client_id]);
    Invoice::factory()->for($project)->for($contract)->create(['number' => 'INV-SEARCH-77']);
    $this->actingAs($manager);

    Livewire::test(GlobalSearch::class)
        ->set('search', 'JOB-2026-031')
        ->assertSee('Riverside Plaza');

    Livewire::test(GlobalSearch::class)
        ->set('search', 'INV-SEARCH-77')
        ->assertSee('INV-SEARCH-77');
});
