<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum NumberSequenceReset: string implements HasLabel
{
    case Never = 'never';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::Yearly => 'Yearly',
        };
    }

    /**
     * The `current_period` value a counter belongs to at the given moment.
     */
    public function periodFor(CarbonInterface $moment): string
    {
        return match ($this) {
            self::Never => 'all',
            self::Yearly => $moment->format('Y'),
        };
    }
}
