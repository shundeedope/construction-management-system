<?php

namespace App\Filament\Subcontractor\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Subcontractor\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\SubcontractorInvoiceCalculationService;
use App\Services\SubcontractorInvoiceWorkflowService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->requiresConfirmation()
                ->record($this->getRecord())
                ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft)
                ->authorize('submit')
                ->successNotificationTitle('Invoice submitted')
                ->action(fn (Invoice $record, SubcontractorInvoiceWorkflowService $workflow) => $workflow->submit($record, auth()->user())),
            Action::make('correctForResubmission')
                ->label('Correct for resubmission')
                ->requiresConfirmation()
                ->record($this->getRecord())
                ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Rejected)
                ->authorize('resubmit')
                ->successNotificationTitle('Invoice reopened for correction')
                ->action(fn (Invoice $record, SubcontractorInvoiceWorkflowService $workflow) => $workflow->prepareForResubmission($record, auth()->user())),
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
