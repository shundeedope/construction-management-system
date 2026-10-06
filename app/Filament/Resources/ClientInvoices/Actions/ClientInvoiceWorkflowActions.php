<?php

namespace App\Filament\Resources\ClientInvoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\ClientInvoiceWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Lifecycle actions shared by the client-invoice table and edit page.
 */
class ClientInvoiceWorkflowActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->requiresConfirmation()
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft)
            ->authorize('approve')
            ->successNotificationTitle('Client invoice approved')
            ->action(fn (Invoice $record, ClientInvoiceWorkflowService $workflow) => $workflow->approve($record, auth()->user()));
    }

    public static function send(): Action
    {
        return Action::make('send')
            ->label('Mark as sent')
            ->requiresConfirmation()
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Approved)
            ->authorize('send')
            ->successNotificationTitle('Client invoice marked as sent')
            ->action(fn (Invoice $record, ClientInvoiceWorkflowService $workflow) => $workflow->send($record, auth()->user()));
    }

    public static function void(): Action
    {
        return Action::make('void')
            ->color('danger')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->schema([
                Textarea::make('void_reason')->required(),
            ])
            ->visible(fn (Invoice $record): bool => $record->status->canTransitionTo(InvoiceStatus::Void))
            ->authorize('void')
            ->successNotificationTitle('Client invoice voided')
            ->action(fn (Invoice $record, array $data, ClientInvoiceWorkflowService $workflow) => $workflow->void($record, auth()->user(), $data['void_reason']));
    }

    public static function previewPdf(): Action
    {
        return Action::make('previewPdf')
            ->label('Preview PDF')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (Invoice $record): string => "Invoice {$record->number}")
            ->modalContent(fn (Invoice $record) => view('filament.components.pdf-preview', [
                'url' => route('client-invoices.pdf', ['invoice' => $record, 'inline' => 1]),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('4xl')
            ->authorize('view');
    }

    public static function downloadPdf(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon(Heroicon::ArrowDownTray)
            ->url(fn (Invoice $record): string => route('client-invoices.pdf', $record))
            ->openUrlInNewTab()
            ->authorize('view');
    }
}
