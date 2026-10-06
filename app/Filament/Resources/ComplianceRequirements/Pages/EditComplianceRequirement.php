<?php

namespace App\Filament\Resources\ComplianceRequirements\Pages;

use App\Filament\Resources\ComplianceRequirements\ComplianceRequirementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComplianceRequirement extends EditRecord
{
    protected static string $resource = ComplianceRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
