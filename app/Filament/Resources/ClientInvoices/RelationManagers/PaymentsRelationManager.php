<?php

namespace App\Filament\Resources\ClientInvoices\RelationManagers;

use App\Enums\PaymentDirection;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
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
        return $ownerRecord instanceof Invoice && auth()->user()?->can('view', $ownerRecord) === true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('amount')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('direction')->badge(),
                TextColumn::make('method')->toggleable(),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('recorder.name')->label('Recorded by')->toggleable(),
                TextColumn::make('deleted_at')->label('Removed')->dateTime()->toggleable(),
            ])
            ->filters([TrashedFilter::make()])
            ->headerActions([
                Action::make('recordPayment')
                    ->label('Record payment')
                    ->schema($this->paymentSchema())
                    ->visible(fn (): bool => $this->getOwnerRecord() instanceof Invoice && $this->getOwnerRecord()->status->acceptsPayments())
                    ->authorize('create', Payment::class)
                    ->successNotificationTitle('Payment recorded')
                    ->action(function (array $data, PaymentService $payments) {
                        /** @var Invoice $invoice */
                        $invoice = $this->getOwnerRecord();

                        /** @var array{amount: int, project_id?: int, direction?: PaymentDirection|string, payment_date: string, method?: string|null, reference?: string|null, notes?: string|null} $attributes */
                        $attributes = [...$data, 'amount' => Money::fromDecimal($data['amount'])->cents];

                        return $payments->record(
                            $invoice,
                            auth()->user(),
                            $attributes,
                        );
                    }),
            ])
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
                ->with('recorder:id,name'));
    }

    /** @return array<int, Component> */
    private function paymentSchema(): array
    {
        return [
            TextInput::make('amount')->numeric()->minValue(0.01)->required()->prefix('$'),
            DatePicker::make('payment_date')->default(now())->required(),
            Select::make('method')->options(['ach' => 'ACH', 'check' => 'Check', 'wire' => 'Wire', 'cash' => 'Cash'])->required(),
            TextInput::make('reference')->maxLength(255),
            Textarea::make('notes')->columnSpanFull(),
        ];
    }
}
