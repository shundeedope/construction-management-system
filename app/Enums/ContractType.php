<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasColor, HasLabel
{
    case Owner = 'owner';
    case Subcontract = 'subcontract';
    case PurchaseOrder = 'purchase_order';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Owner contract',
            self::Subcontract => 'Subcontract',
            self::PurchaseOrder => 'Purchase order',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owner => 'success',
            self::Subcontract => 'info',
            self::PurchaseOrder => 'gray',
        };
    }

    /**
     * Owner contracts bill the client; every other type is a commitment to a vendor.
     */
    public function isRevenue(): bool
    {
        return $this === self::Owner;
    }

    public function requiresVendor(): bool
    {
        return ! $this->isRevenue();
    }
}
