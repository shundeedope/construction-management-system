<?php

namespace App\Filament\Resources\SubcontractorInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\SubcontractorInvoices\SubcontractorInvoiceResource;
use App\Models\Invoice;
use App\Services\SubcontractorInvoiceCalculationService;
use Filament\Resources\Pages\CreateRecord;

class CreateSubcontractorInvoice extends CreateRecord
{
    protected static string $resource = SubcontractorInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = InvoiceStatus::Draft;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Invoice $record */
        $record = $this->record;
        app(SubcontractorInvoiceCalculationService::class)->sync($record);
    }
}
