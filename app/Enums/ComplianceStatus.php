<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ComplianceStatus: string implements HasColor, HasLabel
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::ExpiringSoon => 'Expiring soon',
            self::Expired => 'Expired',
            self::Missing => 'Missing',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::ExpiringSoon => 'warning',
            self::Expired, self::Missing => 'danger',
        };
    }

    /**
     * Statuses that should raise an attention warning in dashboards and vendor panels.
     */
    public function needsAttention(): bool
    {
        return $this !== self::Valid;
    }
}
