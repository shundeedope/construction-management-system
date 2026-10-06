<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Models\Concerns\LogsDomainActivity;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $project_id
 * @property PaymentDirection $direction
 * @property int $amount
 * @property CarbonImmutable $payment_date
 * @property Project $project
 * @property Invoice $invoice
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['invoice_id', 'project_id', 'direction', 'amount', 'payment_date', 'method', 'reference', 'notes', 'recorded_by'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['invoice_id', 'project_id', 'direction', 'amount', 'payment_date', 'method', 'reference'];

    protected function casts(): array
    {
        return ['direction' => PaymentDirection::class, 'amount' => 'integer', 'payment_date' => 'date'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
