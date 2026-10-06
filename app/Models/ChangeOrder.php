<?php

namespace App\Models;

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\ChangeOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $project_id
 * @property int $contract_id
 * @property ChangeOrderType $type
 * @property string $number
 * @property ChangeOrderStatus $status
 * @property Project $project
 * @property Contract $contract
 */
class ChangeOrder extends Model
{
    /** @use HasFactory<ChangeOrderFactory> */
    use HasFactory, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['project_id', 'contract_id', 'type', 'number', 'title', 'description', 'status', 'requested_at', 'submitted_at', 'approved_at', 'rejected_at', 'schedule_impact_days', 'created_by', 'approved_by', 'rejection_reason'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['project_id', 'contract_id', 'type', 'number', 'title', 'status', 'approved_by', 'rejection_reason'];

    protected function casts(): array
    {
        return ['type' => ChangeOrderType::class, 'status' => ChangeOrderStatus::class, 'requested_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'schedule_impact_days' => 'integer'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<ChangeOrderLineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(ChangeOrderLineItem::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
