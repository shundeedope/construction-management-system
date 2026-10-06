<?php

namespace App\Filament\Resources\SubcontractorInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\SubcontractorInvoices\SubcontractorInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSubcontractorInvoices extends ListRecords
{
    protected static string $resource = SubcontractorInvoiceResource::class;

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
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Draft)),
            'awaiting_approval' => Tab::make('Awaiting approval')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Submitted))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->where('status', InvoiceStatus::Submitted)->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [InvoiceStatus::Approved, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', InvoiceStatus::Rejected)),
        ];
    }
}
