<?php

namespace App\Models;

use App\Enums\LeaveTransactionType;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\LeaveTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employee_id
 * @property LeaveTransactionType $type
 * @property Employee $employee
 */
class LeaveTransaction extends Model
{
    /** @use HasFactory<LeaveTransactionFactory> */
    use HasFactory, LogsDomainActivity;

    protected $fillable = ['employee_id', 'type', 'hours', 'effective_date', 'notes', 'created_by'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['employee_id', 'type', 'hours', 'effective_date'];

    protected function casts(): array
    {
        return ['type' => LeaveTransactionType::class, 'hours' => 'decimal:2', 'effective_date' => 'date'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
