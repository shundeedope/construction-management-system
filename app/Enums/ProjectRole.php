<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectRole: string implements HasLabel
{
    case ProjectManager = 'project_manager';
    case SiteSupervisor = 'site_supervisor';
    case Employee = 'employee';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::ProjectManager => 'Project manager',
            self::SiteSupervisor => 'Site supervisor',
            self::Employee => 'Employee',
            self::Other => 'Other',
        };
    }
}
