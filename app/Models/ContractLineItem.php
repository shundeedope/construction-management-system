<?php

namespace App\Models;

use Database\Factories\ContractLineItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contract_id
 * @property int $amount
 * @property int $sort_order
 * @property Contract $contract
 */
class ContractLineItem extends Model
{
    /** @use HasFactory<ContractLineItemFactory> */
    use HasFactory;

    protected $fillable = ['contract_id', 'cost_code', 'description', 'quantity', 'unit', 'unit_price', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'integer', 'amount' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
