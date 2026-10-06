<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VendorType: string implements HasColor, HasLabel
{
    case Subcontractor = 'subcontractor';
    case Supplier = 'supplier';

    public function getLabel(): string
    {
        return match ($this) {
            self::Subcontractor => 'Subcontractor',
            self::Supplier => 'Supplier',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Subcontractor => 'info',
            self::Supplier => 'gray',
        };
    }
}
