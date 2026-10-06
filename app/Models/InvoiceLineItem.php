<?php

namespace App\Models;

use Database\Factories\InvoiceLineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $contract_line_item_id
 * @property int $current_due
 * @property Invoice $invoice
 */
class InvoiceLineItem extends Model
{
    /** @use HasFactory<InvoiceLineItemFactory> */
    use HasFactory;

    protected $fillable = ['invoice_id', 'contract_line_item_id', 'scheduled_value', 'previously_billed', 'work_completed_this_period', 'materials_stored', 'retention_amount', 'current_due', 'sort_order'];

    protected function casts(): array
    {
        return ['scheduled_value' => 'integer', 'previously_billed' => 'integer', 'work_completed_this_period' => 'integer', 'materials_stored' => 'integer', 'retention_amount' => 'integer', 'current_due' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<ContractLineItem, $this> */
    public function contractLineItem(): BelongsTo
    {
        return $this->belongsTo(ContractLineItem::class);
    }
}
