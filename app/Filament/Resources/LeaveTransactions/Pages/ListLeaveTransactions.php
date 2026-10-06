<?php

namespace App\Filament\Resources\LeaveTransactions\Pages;

use App\Filament\Resources\LeaveTransactions\LeaveTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeaveTransactions extends ListRecords
{
    protected static string $resource = LeaveTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
