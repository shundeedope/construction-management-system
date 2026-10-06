<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChangeOrderType: string implements HasColor, HasLabel
{
    case Owner = 'owner';
    case Subcontract = 'subcontract';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Owner change order',
            self::Subcontract => 'Subcontract change order',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owner => 'success',
            self::Subcontract => 'info',
        };
    }

    /**
     * Owner change orders revise contract revenue; subcontract changes revise committed cost.
     */
    public function isRevenue(): bool
    {
        return $this === self::Owner;
    }
}
