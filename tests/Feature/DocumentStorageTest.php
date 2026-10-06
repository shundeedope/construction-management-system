<?php

use App\Enums\DocumentCategory;
use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Project;
use App\Services\DocumentStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->project = Project::factory()->create();
    $this->manager = assignToProject(
        userWithRole(Role::ProjectManager),
        $this->project,
        ProjectRole::ProjectManager,
        canViewFinancials: true,
        canManageDocuments: true,
    );
    $this->document = Document::factory()->for($this->project)->create([
        'category' => DocumentCategory::Plan,
        'uploaded_by' => $this->manager->id,
    ]);
});

test('it stores, replaces, and deletes a private document with auditable metadata', function () {
    $storage = app(DocumentStorageService::class);
    $original = $storage->upload(
        $this->document,
        UploadedFile::fake()->create('site-plan.pdf', 100, 'application/pdf'),
        $this->manager,
    );

    Storage::disk('local')->assertExists($original->getPathRelativeToRoot());

    expect($original->disk)->toBe('local')
        ->and($original->getCustomProperty('original_name'))->toBe('site-plan.pdf')
        ->and($original->getCustomProperty('uploaded_by'))->toBe($this->manager->id)
        ->and($this->document->fresh()->getMedia('documents'))->toHaveCount(1);

    $download = $storage->download($this->document->fresh(), Request::create('/documents'), $this->manager);

    expect($download->headers->get('content-disposition'))->toContain($original->file_name);

    $replacement = $storage->replace(
        $this->document->fresh(),
        UploadedFile::fake()->create('revised-plan.pdf', 100, 'application/pdf'),
        $this->manager,
    );

    Storage::disk('local')->assertMissing($original->getPathRelativeToRoot());
    Storage::disk('local')->assertExists($replacement->getPathRelativeToRoot());
    expect($this->document->fresh()->getMedia('documents'))->toHaveCount(1)
        ->and($replacement->getCustomProperty('original_name'))->toBe('revised-plan.pdf');

    $storage->delete($this->document->fresh(), $this->manager);

    Storage::disk('local')->assertMissing($replacement->getPathRelativeToRoot());
    expect($this->document->fresh()->trashed())->toBeTrue();
});

test('it rejects files with a disallowed extension even when the MIME type is allowed', function () {
    expect(fn () => app(DocumentStorageService::class)->upload(
        $this->document,
        UploadedFile::fake()->create('unsafe.php', 100, 'application/pdf'),
        $this->manager,
    ))->toThrow(ValidationException::class);

    expect(fn () => app(DocumentStorageService::class)->upload(
        $this->document,
        UploadedFile::fake()->create('unsafe.txt', 100, 'application/x-php'),
        $this->manager,
    ))->toThrow(ValidationException::class);

    expect(fn () => app(DocumentStorageService::class)->upload(
        $this->document,
        UploadedFile::fake()->create('too-large.pdf', 10_241, 'application/pdf'),
        $this->manager,
    ))->toThrow(ValidationException::class);
});

test('it does not create a document when project-scoped document management is denied', function () {
    $employee = assignToProject(userWithRole(Role::Employee), $this->project);
    $pendingDocument = Document::factory()->make([
        'project_id' => $this->project->id,
        'category' => DocumentCategory::Plan,
        'uploaded_by' => $employee->id,
    ]);

    expect(fn () => app(DocumentStorageService::class)->create(
        $pendingDocument,
        UploadedFile::fake()->create('site-plan.pdf', 100, 'application/pdf'),
        $employee,
    ))->toThrow(HttpException::class);

    expect(Document::query()->count())->toBe(1);
});
