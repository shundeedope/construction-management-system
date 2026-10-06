<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $employee_number
 * @property bool $is_active
 * @property Carbon|null $hired_at
 * @property Carbon|null $terminated_at
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'employee_number', 'first_name', 'last_name', 'email', 'phone', 'hired_at', 'terminated_at', 'is_active'];

    protected function casts(): array
    {
        return ['hired_at' => 'date', 'terminated_at' => 'date', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<LeaveTransaction, $this> */
    public function leaveTransactions(): HasMany
    {
        return $this->hasMany(LeaveTransaction::class);
    }

    /** @return HasMany<ComplianceRecord, $this> */
    public function complianceRecords(): HasMany
    {
        return $this->hasMany(ComplianceRecord::class);
    }
}
