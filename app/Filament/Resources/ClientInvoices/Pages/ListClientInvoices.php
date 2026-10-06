<?php

namespace App\Filament\Resources\ClientInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\ClientInvoices\ClientInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClientInvoices extends ListRecords
{
    protected static string $resource = ClientInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Draft))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->where('status', InvoiceStatus::Draft)->count()),
            'awaiting_payment' => Tab::make('Awaiting payment')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', self::unpaidStatuses()))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->whereIn('status', self::unpaidStatuses())->count()),
            'overdue' => Tab::make('Overdue')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', self::unpaidStatuses())->whereDate('due_date', '<', today()))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->whereIn('status', self::unpaidStatuses())->whereDate('due_date', '<', today())->count())
                ->badgeColor('danger'),
            'paid' => Tab::make('Paid')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Paid)),
        ];
    }

    /** @return array<int, InvoiceStatus> */
    private static function unpaidStatuses(): array
    {
        return [InvoiceStatus::Approved, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid];
    }
}
