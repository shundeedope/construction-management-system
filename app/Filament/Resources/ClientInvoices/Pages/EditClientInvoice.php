<?php

namespace App\Filament\Resources\ClientInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\HasReadOnlyLockedForm;
use App\Filament\Resources\ClientInvoices\Actions\ClientInvoiceWorkflowActions;
use App\Filament\Resources\ClientInvoices\ClientInvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceCalculationService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditClientInvoice extends EditRecord
{
    use HasReadOnlyLockedForm;

    protected static string $resource = ClientInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ClientInvoiceWorkflowActions::approve()->record($this->getRecord()),
            ClientInvoiceWorkflowActions::send()->record($this->getRecord()),
            ClientInvoiceWorkflowActions::previewPdf()->record($this->getRecord()),
            ClientInvoiceWorkflowActions::downloadPdf()->record($this->getRecord()),
            ClientInvoiceWorkflowActions::void()->record($this->getRecord()),
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        Gate::authorize('update', $this->record);

        /** @var Invoice $record */
        $record = $this->record;
        abort_unless($record->status === InvoiceStatus::Draft, 403);
    }

    protected function afterSave(): void
    {
        /** @var Invoice $record */
        $record = $this->record;
        app(InvoiceCalculationService::class)->sync($record);
    }
}
