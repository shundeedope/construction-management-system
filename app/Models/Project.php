<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Models\Concerns\LogsDomainActivity;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $job_number
 * @property string $name
 * @property int $client_id
 * @property int|null $project_manager_id
 * @property ProjectStatus $status
 *
 * @method Builder<static> visibleTo(User $user)
 * @method Builder<static> visibleToVendor(User $user)
 * @method Builder<static> financiallyVisibleTo(User $user)
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, LogsDomainActivity, SoftDeletes;

    protected $fillable = ['job_number', 'name', 'client_id', 'project_manager_id', 'description', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'start_date', 'expected_completion_date', 'actual_completion_date', 'status', 'internal_notes'];

    /** @var array<int, string> */
    protected array $activityLogAttributes = ['job_number', 'name', 'client_id', 'project_manager_id', 'status', 'start_date', 'expected_completion_date', 'actual_completion_date'];

    protected function casts(): array
    {
        return ['status' => ProjectStatus::class, 'start_date' => 'date', 'expected_completion_date' => 'date', 'actual_completion_date' => 'date'];
    }

    /**
     * Restrict a project query to what the user is allowed to see. Roles holding the
     * "view all projects" capability see every project; everybody else sees only the projects
     * they are assigned to in `project_user`. This is the query-level counterpart to
     * ProjectPolicy::view() and must be applied to every Filament table and select.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ViewAllProjects->value)) {
            return;
        }

        $query->whereHas('users', fn (Builder $assigned) => $assigned->whereKey($user->getKey()));
    }

    /**
     * Restrict a project query to projects whose financial records the user can view.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function financiallyVisibleTo(Builder $query, User $user): void
    {
        if ($user->can(Permission::ViewAllFinancials->value)) {
            return;
        }

        $query->whereHas('users', fn (Builder $assigned) => $assigned
            ->whereKey($user->getKey())
            ->where('project_user.can_view_financials', true));
    }

    /**
     * Restrict portal projects to those with a contract owned by the vendor identity.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function visibleToVendor(Builder $query, User $user): void
    {
        $query->whereHas('contracts', fn (Builder $contracts) => $contracts->where('vendor_id', $user->vendor_id));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /** @return BelongsToMany<User, $this, ProjectAssignment> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ProjectAssignment::class)
            ->withPivot(['project_role', 'can_view_financials', 'can_manage_documents'])
            ->withTimestamps();
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /** @return HasMany<ChangeOrder, $this> */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<ComplianceRecord, $this> */
    public function complianceRecords(): HasMany
    {
        return $this->hasMany(ComplianceRecord::class);
    }
}
