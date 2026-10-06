<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Payment;
use App\Models\Project;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Project && auth()->user()?->can('viewFinancials', $ownerRecord) === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('invoice.number')->label('Invoice')->searchable(),
                TextColumn::make('amount')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('direction')->badge(),
                TextColumn::make('method')->toggleable(),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('recorder.name')->label('Recorded by')->toggleable(),
                TextColumn::make('deleted_at')->label('Removed')->dateTime()->toggleable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                Action::make('removePayment')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => ! $record->trashed())
                    ->authorize('delete')
                    ->successNotificationTitle('Payment removed')
                    ->action(fn (Payment $record, PaymentService $payments) => $payments->remove($record, auth()->user())),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([SoftDeletingScope::class])
                ->with(['invoice:id,number', 'recorder:id,name']));
    }
}
