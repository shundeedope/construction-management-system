<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComplianceSubject: string implements HasLabel
{
    case Employee = 'employee';
    case Vendor = 'vendor';
    case Project = 'project';

    public function getLabel(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Vendor => 'Vendor',
            self::Project => 'Project',
        };
    }

    /**
     * The compliance record column holding this subject's foreign key.
     */
    public function foreignKey(): string
    {
        return match ($this) {
            self::Employee => 'employee_id',
            self::Vendor => 'vendor_id',
            self::Project => 'project_id',
        };
    }
}
