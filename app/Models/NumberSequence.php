<?php

namespace App\Models;

use App\Enums\NumberSequenceReset;
use Database\Factories\NumberSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $scope
 * @property string $format
 * @property int $next_value
 * @property int $padding
 * @property NumberSequenceReset $reset_frequency
 * @property string $current_period
 */
class NumberSequence extends Model
{
    /** @use HasFactory<NumberSequenceFactory> */
    use HasFactory;

    protected $fillable = ['key', 'scope', 'format', 'next_value', 'padding', 'reset_frequency', 'current_period'];

    protected function casts(): array
    {
        return ['reset_frequency' => NumberSequenceReset::class, 'next_value' => 'integer', 'padding' => 'integer'];
    }
}
