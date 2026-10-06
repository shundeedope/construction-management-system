<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeaveTransactionType: string implements HasColor, HasLabel
{
    case Accrual = 'accrual';
    case Usage = 'usage';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Accrual => 'Accrual',
            self::Usage => 'Usage',
            self::Adjustment => 'Adjustment',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Accrual => 'success',
            self::Usage => 'warning',
            self::Adjustment => 'info',
        };
    }

    /**
     * Balance is accrual + signed adjustment - usage, so usage is the only inverted term.
     */
    public function balanceSign(): int
    {
        return $this === self::Usage ? -1 : 1;
    }
}
