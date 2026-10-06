<?php

namespace App\Filament\Resources\ChangeOrders\Actions;

use App\Enums\ChangeOrderStatus;
use App\Models\ChangeOrder;
use App\Services\ChangeOrderWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Lifecycle actions shared by the change-order table and edit page.
 */
class ChangeOrderWorkflowActions
{
    public static function submit(): Action
    {
        return Action::make('submit')
            ->label('Submit for approval')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (ChangeOrder $record): bool => $record->status === ChangeOrderStatus::Draft)
            ->authorize('submit')
            ->successNotificationTitle('Change order submitted for approval')
            ->action(fn (ChangeOrder $record, ChangeOrderWorkflowService $workflow) => $workflow->submit($record, auth()->user()));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->requiresConfirmation()
            ->color('success')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->visible(fn (ChangeOrder $record): bool => $record->status === ChangeOrderStatus::Submitted)
            ->authorize('approve')
            ->successNotificationTitle('Change order approved')
            ->action(fn (ChangeOrder $record, ChangeOrderWorkflowService $workflow) => $workflow->approve($record, auth()->user()));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->color('danger')
            ->icon(Heroicon::OutlinedXCircle)
            ->schema([
                Textarea::make('rejection_reason')->required(),
            ])
            ->visible(fn (ChangeOrder $record): bool => $record->status === ChangeOrderStatus::Submitted)
            ->authorize('reject')
            ->successNotificationTitle('Change order rejected')
            ->action(fn (ChangeOrder $record, array $data, ChangeOrderWorkflowService $workflow) => $workflow->reject($record, auth()->user(), $data['rejection_reason']));
    }
}
