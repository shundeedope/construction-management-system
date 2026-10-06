<?php

namespace App\Filament\Resources\OwnerContracts\Actions;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\ContractWorkflowService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Lifecycle actions shared by owner-contract and subcontract tables and pages.
 */
class ContractWorkflowActions
{
    public static function submitForApproval(): Action
    {
        return Action::make('submitForApproval')
            ->label('Submit for approval')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Draft)
            ->successNotificationTitle('Contract submitted for approval')
            ->action(fn (Contract $record, ContractWorkflowService $workflow) => $workflow->submitForApproval($record, auth()->user()));
    }

    public static function execute(): Action
    {
        return Action::make('execute')
            ->requiresConfirmation()
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (Contract $record): bool => $record->status === ContractStatus::PendingApproval)
            ->authorize('execute')
            ->successNotificationTitle('Contract executed')
            ->action(fn (Contract $record, ContractWorkflowService $workflow) => $workflow->execute($record, auth()->user()));
    }

    public static function close(): Action
    {
        return Action::make('close')
            ->requiresConfirmation()
            ->icon(Heroicon::OutlinedLockClosed)
            ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Executed)
            ->authorize('close')
            ->successNotificationTitle('Contract closed')
            ->action(fn (Contract $record, ContractWorkflowService $workflow) => $workflow->close($record, auth()->user()));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->color('danger')
            ->requiresConfirmation()
            ->icon(Heroicon::OutlinedNoSymbol)
            ->visible(fn (Contract $record): bool => $record->status->canTransitionTo(ContractStatus::Cancelled))
            ->authorize('cancel')
            ->successNotificationTitle('Contract cancelled')
            ->action(fn (Contract $record, ContractWorkflowService $workflow) => $workflow->cancel($record, auth()->user()));
    }
}
