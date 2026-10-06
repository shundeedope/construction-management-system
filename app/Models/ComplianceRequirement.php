<?php

namespace App\Models;

use App\Enums\ComplianceSubject;
use Database\Factories\ComplianceRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property ComplianceSubject $applies_to
 * @property bool $is_required
 * @property int $default_reminder_days
 * @property bool $is_active
 */
class ComplianceRequirement extends Model
{
    /** @use HasFactory<ComplianceRequirementFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'applies_to', 'is_required', 'default_reminder_days', 'is_active'];

    protected function casts(): array
    {
        return ['applies_to' => ComplianceSubject::class, 'is_required' => 'boolean', 'default_reminder_days' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<ComplianceRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(ComplianceRecord::class);
    }
}
