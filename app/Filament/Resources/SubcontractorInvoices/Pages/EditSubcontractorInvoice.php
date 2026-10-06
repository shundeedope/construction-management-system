<?php

namespace App\Filament\Resources\SubcontractorInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Concerns\HasReadOnlyLockedForm;
use App\Filament\Resources\SubcontractorInvoices\Actions\SubcontractorInvoiceWorkflowActions;
use App\Filament\Resources\SubcontractorInvoices\SubcontractorInvoiceResource;
use App\Models\Invoice;
use App\Services\SubcontractorInvoiceCalculationService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditSubcontractorInvoice extends EditRecord
{
    use HasReadOnlyLockedForm;

    protected static string $resource = SubcontractorInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubcontractorInvoiceWorkflowActions::submit()->record($this->getRecord()),
            SubcontractorInvoiceWorkflowActions::approve()->record($this->getRecord()),
            SubcontractorInvoiceWorkflowActions::reject()->record($this->getRecord()),
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
        app(SubcontractorInvoiceCalculationService::class)->sync($record);
    }
}
