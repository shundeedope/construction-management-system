<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $client_id
 * @property int|null $vendor_id
 * @property ContractType $type
 * @property string $number
 * @property ContractStatus $status
 * @property int $original_amount
 * @property int $retention_basis_points
 * @property Project $project
 *
 * @method Builder<static> ownedByVendor(User $user)
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['project_id', 'client_id', 'vendor_id', 'type', 'number', 'title', 'description', 'status', 'original_amount', 'retention_basis_points', 'executed_at', 'start_date', 'completion_date', 'created_by', 'approved_by'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['project_id', 'type', 'number', 'title', 'status', 'original_amount', 'retention_basis_points', 'executed_at', 'approved_by'];

    protected function casts(): array
    {
        return ['type' => ContractType::class, 'status' => ContractStatus::class, 'original_amount' => 'integer', 'retention_basis_points' => 'integer', 'executed_at' => 'datetime', 'start_date' => 'date', 'completion_date' => 'date'];
    }

    /**
     * @param  Builder<Contract>  $query
     */
    #[Scope]
    protected function ownedByVendor(Builder $query, User $user): void
    {
        $query->where('vendor_id', $user->vendor_id);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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

    /** @return HasMany<ContractLineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(ContractLineItem::class);
    }

    /** @return HasMany<ChangeOrder, $this> */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
