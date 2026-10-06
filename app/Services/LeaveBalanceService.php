<?php

namespace App\Services;

use App\Enums\LeaveTransactionType;
use App\Models\Employee;
use App\Models\LeaveTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class LeaveBalanceService
{
    public function balanceFor(Employee $employee): float
    {
        return (float) $employee->leaveTransactions()
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN -hours ELSE hours END), 0) AS balance', [LeaveTransactionType::Usage->value])
            ->value('balance');
    }

    public function record(Employee $employee, LeaveTransactionType $type, float $hours, string $effectiveDate, ?string $notes, User $actor): LeaveTransaction
    {
        Gate::forUser($actor)->authorize('create', LeaveTransaction::class);

        return $employee->leaveTransactions()->create([
            'type' => $type,
            'hours' => $type === LeaveTransactionType::Usage ? abs($hours) : $hours,
            'effective_date' => $effectiveDate,
            'notes' => $notes,
            'created_by' => $actor->getKey(),
        ]);
    }
}
