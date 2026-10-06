<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\InvoiceFactory;
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
 * @property int $contract_id
 * @property InvoiceType $type
 * @property string $number
 * @property InvoiceStatus $status
 * @property int $subtotal
 * @property int $retention_amount
 * @property int $total
 * @property Project $project
 * @property Contract $contract
 *
 * @method Builder<static> ownedByVendor(User $user)
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['project_id', 'contract_id', 'type', 'number', 'status', 'billing_period_start', 'billing_period_end', 'issue_date', 'due_date', 'subtotal', 'retention_amount', 'total', 'sent_at', 'approved_at', 'voided_at', 'created_by', 'approved_by', 'rejection_reason', 'void_reason', 'notes'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['project_id', 'contract_id', 'type', 'number', 'status', 'subtotal', 'retention_amount', 'total', 'sent_at', 'approved_by', 'void_reason'];

    protected function casts(): array
    {
        return ['type' => InvoiceType::class, 'status' => InvoiceStatus::class, 'billing_period_start' => 'date', 'billing_period_end' => 'date', 'issue_date' => 'date', 'due_date' => 'date', 'subtotal' => 'integer', 'retention_amount' => 'integer', 'total' => 'integer', 'sent_at' => 'datetime', 'approved_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    #[Scope]
    protected function ownedByVendor(Builder $query, User $user): void
    {
        $query->where('type', InvoiceType::Subcontractor)
            ->whereHas('contract', function (Builder $contract) use ($user): void {
                /** @var Builder<Contract> $contract */
                $contract->ownedByVendor($user);
            });
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

    /** @return HasMany<InvoiceLineItem, $this> */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
