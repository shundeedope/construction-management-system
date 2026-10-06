<?php

namespace App\Filament\Resources\ChangeOrders\Pages;

use App\Enums\ChangeOrderStatus;
use App\Filament\Resources\ChangeOrders\ChangeOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListChangeOrders extends ListRecords
{
    protected static string $resource = ChangeOrderResource::class;

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
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ChangeOrderStatus::Draft)),
            'awaiting_approval' => Tab::make('Awaiting approval')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ChangeOrderStatus::Submitted))
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->where('status', ChangeOrderStatus::Submitted)->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ChangeOrderStatus::Approved)),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ChangeOrderStatus::Rejected)),
        ];
    }
}
