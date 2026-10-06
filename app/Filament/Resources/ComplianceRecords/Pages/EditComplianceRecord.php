<?php

namespace App\Filament\Resources\ComplianceRecords\Pages;

use App\Filament\Resources\ComplianceRecords\ComplianceRecordResource;
use App\Models\ComplianceRecord;
use App\Services\ComplianceStatusService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditComplianceRecord extends EditRecord
{
    protected static string $resource = ComplianceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var ComplianceRecord $record */
        $record = $this->record;
        app(ComplianceStatusService::class)->refresh($record);
    }
}
