<?php

namespace App\Filament\Resources\LeaveTransactions\Pages;

use App\Filament\Resources\LeaveTransactions\LeaveTransactionResource;
use Filament\Resources\Pages\EditRecord;

class EditLeaveTransaction extends EditRecord
{
    protected static string $resource = LeaveTransactionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        abort(403);
    }
}
