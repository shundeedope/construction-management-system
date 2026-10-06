<?php

use App\Enums\DocumentCategory;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Project;
use App\Services\DocumentStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->project = Project::factory()->create();
    $manager = assignToProject(
        userWithRole(Role::ProjectManager),
        $this->project,
        ProjectRole::ProjectManager,
        canViewFinancials: true,
        canManageDocuments: true,
    );
    $this->plan = Document::factory()->for($this->project)->create(['category' => DocumentCategory::Plan, 'uploaded_by' => $manager->id]);
    $this->financialDocument = Document::factory()->for($this->project)->create(['category' => DocumentCategory::Contract, 'uploaded_by' => $manager->id]);
    $storage = app(DocumentStorageService::class);
    $storage->upload($this->plan, UploadedFile::fake()->create('site-plan.pdf', 100, 'application/pdf'), $manager);
    $storage->upload($this->financialDocument, UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'), $manager);
});

test('users outside a project cannot download documents through direct URLs', function () {
    $this->actingAs(userWithRole(Role::Employee))->get(route('documents.download', $this->plan))->assertForbidden();
});

test('users without financial access cannot download financial documents', function () {
    $supervisor = assignToProject(userWithRole(Role::SiteSupervisor), $this->project, ProjectRole::SiteSupervisor, canManageDocuments: true);

    $this->actingAs($supervisor)->get(route('documents.download', $this->financialDocument))->assertForbidden();
});

test('a project user can download a permitted private document', function () {
    $employee = assignToProject(userWithRole(Role::Employee), $this->project);

    $this->actingAs($employee)
        ->get(route('documents.download', $this->plan))
        ->assertSuccessful()
        ->assertHeader('content-disposition', 'attachment; filename="'.$this->plan->getFirstMedia('documents')->file_name.'"');
});
