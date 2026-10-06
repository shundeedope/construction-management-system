<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasColor, HasLabel
{
    case Planning = 'planning';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planning => 'gray',
            self::Active => 'success',
            self::OnHold => 'warning',
            self::Completed => 'info',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Statuses that still consume budget and appear in active project reporting.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planning, self::Active, self::OnHold], strict: true);
    }
}
