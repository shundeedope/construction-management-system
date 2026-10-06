<?php

namespace App\Filament\Resources\LeaveTransactions\Pages;

use App\Enums\LeaveTransactionType;
use App\Filament\Resources\LeaveTransactions\LeaveTransactionResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLeaveTransaction extends CreateRecord
{
    protected static string $resource = LeaveTransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Employee $employee */
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return app(LeaveBalanceService::class)->record(
            $employee,
            LeaveTransactionType::from($data['type']),
            (float) $data['hours'],
            $data['effective_date'],
            $data['notes'] ?? null,
            $actor,
        );
    }
}
