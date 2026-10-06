<?php

namespace App\Filament\Resources\SubcontractorInvoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\SubcontractorInvoiceWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Lifecycle actions shared by the subcontractor-invoice table and edit page.
 */
class SubcontractorInvoiceWorkflowActions
{
    public static function submit(): Action
    {
        return Action::make('submit')
            ->requiresConfirmation()
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft)
            ->authorize('submit')
            ->successNotificationTitle('Subcontractor invoice submitted')
            ->action(fn (Invoice $record, SubcontractorInvoiceWorkflowService $workflow) => $workflow->submit($record, auth()->user()));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->requiresConfirmation()
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Submitted)
            ->authorize('approve')
            ->successNotificationTitle('Subcontractor invoice approved')
            ->action(fn (Invoice $record, SubcontractorInvoiceWorkflowService $workflow) => $workflow->approve($record, auth()->user()));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->color('danger')
            ->icon(Heroicon::OutlinedXCircle)
            ->schema([
                Textarea::make('rejection_reason')->required(),
            ])
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Submitted)
            ->authorize('reject')
            ->successNotificationTitle('Subcontractor invoice rejected')
            ->action(fn (Invoice $record, array $data, SubcontractorInvoiceWorkflowService $workflow) => $workflow->reject($record, auth()->user(), $data['rejection_reason']));
    }
}
