<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CompanySettingType: string implements HasLabel
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';
    case Encrypted = 'encrypted';

    public function getLabel(): string
    {
        return match ($this) {
            self::String => 'Text',
            self::Integer => 'Number',
            self::Boolean => 'Yes / no',
            self::Json => 'JSON',
            self::Encrypted => 'Encrypted secret',
        };
    }

    /**
     * Encrypted values are never logged, exported, or shown in plain text.
     */
    public function isSensitive(): bool
    {
        return $this === self::Encrypted;
    }
}
