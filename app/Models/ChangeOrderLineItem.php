<?php

namespace App\Models;

use Database\Factories\ChangeOrderLineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $change_order_id
 * @property int $amount
 * @property int $sort_order
 * @property ChangeOrder $changeOrder
 */
class ChangeOrderLineItem extends Model
{
    /** @use HasFactory<ChangeOrderLineItemFactory> */
    use HasFactory;

    protected $fillable = ['change_order_id', 'contract_line_item_id', 'cost_code', 'description', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<ChangeOrder, $this> */
    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }

    /** @return BelongsTo<ContractLineItem, $this> */
    public function contractLineItem(): BelongsTo
    {
        return $this->belongsTo(ContractLineItem::class);
    }
}
