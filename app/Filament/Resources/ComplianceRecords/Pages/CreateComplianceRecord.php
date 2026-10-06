<?php

namespace App\Filament\Resources\ComplianceRecords\Pages;

use App\Filament\Resources\ComplianceRecords\ComplianceRecordResource;
use App\Models\ComplianceRecord;
use App\Services\ComplianceStatusService;
use Filament\Resources\Pages\CreateRecord;

class CreateComplianceRecord extends CreateRecord
{
    protected static string $resource = ComplianceRecordResource::class;

    protected function afterCreate(): void
    {
        /** @var ComplianceRecord $record */
        $record = $this->record;
        app(ComplianceStatusService::class)->refresh($record);
    }
}
