<?php

namespace App\Models;

use App\Enums\TimeEntryStatus;
use App\Models\Concerns\LogsDomainActivity;
use Carbon\CarbonImmutable;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $project_id
 * @property TimeEntryStatus $status
 * @property CarbonImmutable $work_date
 * @property Employee $employee
 * @property Project $project
 */
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['employee_id', 'project_id', 'work_date', 'regular_hours', 'overtime_hours', 'is_billable', 'description', 'status', 'submitted_at', 'approved_at', 'approved_by', 'rejection_reason'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['employee_id', 'project_id', 'work_date', 'regular_hours', 'overtime_hours', 'status', 'approved_by', 'rejection_reason'];

    protected function casts(): array
    {
        return ['status' => TimeEntryStatus::class, 'work_date' => 'date', 'regular_hours' => 'decimal:2', 'overtime_hours' => 'decimal:2', 'is_billable' => 'boolean', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
