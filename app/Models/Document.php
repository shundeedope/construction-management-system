<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property DocumentCategory $category
 * @property Project $project
 */
class Document extends Model implements HasMedia
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, InteractsWithMedia, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['project_id', 'documentable_type', 'documentable_id', 'name', 'description', 'category', 'external_url', 'google_drive_file_id', 'google_drive_folder_id', 'uploaded_by'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['project_id', 'name', 'category', 'external_url', 'google_drive_file_id'];

    protected function casts(): array
    {
        return ['category' => DocumentCategory::class];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk('local')
            ->singleFile();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
