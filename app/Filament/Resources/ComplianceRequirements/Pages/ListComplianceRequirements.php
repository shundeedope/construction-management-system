<?php

namespace App\Filament\Resources\ComplianceRequirements\Pages;

use App\Filament\Resources\ComplianceRequirements\ComplianceRequirementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComplianceRequirements extends ListRecords
{
    protected static string $resource = ComplianceRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
