<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\DocumentCategory;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\DocumentStorageService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('viewAny', Document::class)
            && ($user->vendor_id !== null
                ? ($ownerRecord instanceof Invoice && $user->ownsVendorInvoice($ownerRecord)) || ($ownerRecord instanceof Contract && $user->ownsVendorContract($ownerRecord))
                : $user->can('view', self::projectFor($ownerRecord)));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('category')->badge(),
                TextColumn::make('media.file_name')->label('Stored file')->toggleable(),
                TextColumn::make('media.mime_type')->label('MIME type')->toggleable(),
                TextColumn::make('uploader.name')->label('Uploaded by')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('uploadDocument')
                    ->label('Upload document')
                    ->schema($this->documentSchema())
                    ->visible(fn (): bool => $this->canManageDocuments())
                    ->authorize('create', Document::class)
                    ->action(function (array $data, DocumentStorageService $storage): void {
                        $document = $this->newDocument($data);
                        $file = $data['file'] ?? null;

                        abort_unless($file instanceof UploadedFile, 422);

                        $storage->create($document, $file, auth()->user());
                    })
                    ->successNotificationTitle('Document uploaded'),
            ])
            ->recordActions([
                Action::make('downloadDocument')
                    ->label('Download')
                    ->url(fn (Document $record): string => route('documents.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Document $record): bool => $record->getFirstMedia('documents') !== null)
                    ->authorize('download'),
                Action::make('replaceDocument')
                    ->label('Replace')
                    ->schema([$this->documentFileField()])
                    ->visible(fn (): bool => $this->canManageDocuments())
                    ->authorize('update')
                    ->action(function (Document $record, array $data, DocumentStorageService $storage): void {
                        $file = $data['file'] ?? null;

                        abort_unless($file instanceof UploadedFile, 422);

                        $storage->replace($record, $file, auth()->user());
                    })
                    ->successNotificationTitle('Document replaced'),
                Action::make('deleteDocument')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize('delete')
                    ->action(fn (Document $record, DocumentStorageService $storage) => $storage->delete($record, auth()->user()))
                    ->successNotificationTitle('Document deleted'),
            ])
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with(['uploader:id,name', 'media']);

                if (auth()->user()?->vendor_id === null && ! auth()->user()?->can('viewFinancials', $this->project())) {
                    $query->whereNotIn('category', array_map(
                        fn (DocumentCategory $category): string => $category->value,
                        array_filter(DocumentCategory::cases(), fn (DocumentCategory $category): bool => $category->isFinancial()),
                    ));
                }

                return $query;
            });
    }

    /** @return array<int, mixed> */
    private function documentSchema(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),
            Select::make('category')->options(DocumentCategory::class)->required(),
            Textarea::make('description')->maxLength(65535),
            $this->documentFileField(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function newDocument(array $data): Document
    {
        $owner = $this->getOwnerRecord();
        $document = new Document([
            'name' => $data['name'],
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        $document->project()->associate($this->project());

        if (! $owner instanceof Project) {
            $document->documentable()->associate($owner);
        }

        return $document;
    }

    private function project(): Project
    {
        return self::projectFor($this->getOwnerRecord());
    }

    private static function projectFor(Model $ownerRecord): Project
    {
        if ($ownerRecord instanceof Project) {
            return $ownerRecord;
        }

        assert(method_exists($ownerRecord, 'project'));

        /** @var Project $project */
        $project = $ownerRecord->getRelationValue('project');

        return $project;
    }

    private function canManageDocuments(): bool
    {
        $user = auth()->user();

        return $user?->vendor_id !== null
            || $user?->canManageDocumentsFor($this->project()) === true;
    }

    private function documentFileField(): FileUpload
    {
        return FileUpload::make('file')
            ->acceptedFileTypes(DocumentStorageService::ALLOWED_MIME_TYPES)
            ->rules(['extensions:'.implode(',', DocumentStorageService::ALLOWED_EXTENSIONS)])
            ->maxSize(10240)
            ->storeFiles(false)
            ->required();
    }
}
