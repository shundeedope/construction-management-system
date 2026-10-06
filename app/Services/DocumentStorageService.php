<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentStorageService
{
    /** @var array<int, string> */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ];

    /** @var array<int, string> */
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'txt'];

    public function create(Document $document, UploadedFile $file, User $actor): Media
    {
        Gate::forUser($actor)->authorize('create', Document::class);

        if ($actor->vendor_id !== null) {
            Gate::forUser($actor)->authorize('view', $document);
        } else {
            abort_unless($actor->canManageDocumentsFor($document->project), 403);
        }

        $this->validate($file);

        return DB::transaction(function () use ($document, $file, $actor): Media {
            $document->save();

            return $this->attach($document, $file, $actor);
        });
    }

    public function upload(Document $document, UploadedFile $file, User $actor): Media
    {
        Gate::forUser($actor)->authorize('update', $document);

        $this->validate($file);

        return $this->attach($document, $file, $actor);
    }

    public function download(Document $document, Request $request, User $actor): StreamedResponse
    {
        Gate::forUser($actor)->authorize('download', $document);

        $media = $document->getFirstMedia('documents');

        abort_if($media === null, 404);

        return $media->toResponse($request);
    }

    private function attach(Document $document, UploadedFile $file, User $actor): Media
    {
        return $document
            ->addMedia($file)
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->usingFileName(Str::uuid().'.'.mb_strtolower($file->getClientOriginalExtension()))
            ->withCustomProperties([
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => $actor->getKey(),
            ])
            ->toMediaCollection('documents');
    }

    public function replace(Document $document, UploadedFile $file, User $actor): Media
    {
        return $this->upload($document, $file, $actor);
    }

    public function delete(Document $document, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $document);

        $document->clearMediaCollection('documents');
        $document->delete();
    }

    private function validate(UploadedFile $file): void
    {
        Validator::make(['file' => $file], [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
                'extensions:'.implode(',', self::ALLOWED_EXTENSIONS),
                'max:10240',
            ],
        ])->validate();
    }
}
