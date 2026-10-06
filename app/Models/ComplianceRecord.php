<?php

namespace App\Models;

use App\Enums\ComplianceStatus;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\ComplianceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $compliance_requirement_id
 * @property int|null $employee_id
 * @property int|null $vendor_id
 * @property int|null $project_id
 * @property ComplianceStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $issued_at
 * @property ComplianceRequirement|null $requirement
 * @property Project|null $project
 */
class ComplianceRecord extends Model
{
    /** @use HasFactory<ComplianceRecordFactory> */
    use HasFactory, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['compliance_requirement_id', 'employee_id', 'vendor_id', 'project_id', 'document_id', 'issued_at', 'expires_at', 'status', 'notes', 'verified_by', 'verified_at'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['compliance_requirement_id', 'employee_id', 'vendor_id', 'project_id', 'status', 'expires_at', 'verified_by'];

    protected function casts(): array
    {
        return ['status' => ComplianceStatus::class, 'issued_at' => 'date', 'expires_at' => 'date', 'verified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $subjects = array_filter([
                'employee_id' => $record->employee_id,
                'vendor_id' => $record->vendor_id,
                'project_id' => $record->project_id,
            ], fn (?int $id): bool => $id !== null);

            if (count($subjects) !== 1) {
                throw ValidationException::withMessages(['subject' => 'Exactly one compliance subject is required.']);
            }

            $requirement = $record->relationLoaded('requirement') ? $record->requirement : $record->requirement()->first();

            if ($requirement !== null && ! array_key_exists($requirement->applies_to->foreignKey(), $subjects)) {
                throw ValidationException::withMessages(['subject' => 'The subject must match the requirement type.']);
            }
        });
    }

    /** @return BelongsTo<ComplianceRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class, 'compliance_requirement_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
