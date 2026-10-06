<?php

namespace App\Filament\Resources\ClientInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\ClientInvoices\ClientInvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceCalculationService;
use Filament\Resources\Pages\CreateRecord;

class CreateClientInvoice extends CreateRecord
{
    protected static string $resource = ClientInvoiceResource::class;

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
        app(InvoiceCalculationService::class)->sync($record);
    }
}
