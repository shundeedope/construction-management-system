<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Project assignment is the second access boundary: a global role decides what a user may
     * do, and `project_user` decides which projects they may do it to. Roles holding the
     * "view all projects" capability (administration, accounting, executives) bypass it.
     */
    public function hasProjectAccess(Project $project): bool
    {
        return $this->can(Permission::ViewAllProjects->value)
            || $this->isAssignedTo($project);
    }

    public function isAssignedTo(Project $project): bool
    {
        return $this->assignmentFor($project) !== null;
    }

    /**
     * Financial visibility needs the capability *and*, for project-scoped roles, the
     * `can_view_financials` flag on the assignment itself.
     */
    public function canViewFinancialsFor(Project $project): bool
    {
        if ($this->can(Permission::ViewAllFinancials->value)) {
            return true;
        }

        if (! $this->can(Permission::ViewFinancials->value)) {
            return false;
        }

        $assignment = $this->assignmentFor($project);

        return $assignment !== null && $assignment->can_view_financials;
    }

    public function canManageDocumentsFor(Project $project): bool
    {
        if (! $this->can(Permission::ManageDocuments->value)) {
            return false;
        }

        if ($this->can(Permission::ViewAllProjects->value)) {
            return true;
        }

        $assignment = $this->assignmentFor($project);

        return $assignment !== null && $assignment->can_manage_documents;
    }

    /**
     * The pivot row describing this user's membership of the project, or null when unassigned.
     */
    public function assignmentFor(Project $project): ?ProjectAssignment
    {
        return $this->projects()
            ->whereKey($project->getKey())
            ->first()
            ?->pivot;
    }

    /** @return HasOne<Employee, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'subcontractor' => $this->vendor_id !== null,
            'admin' => $this->vendor_id === null && $this->can(Permission::ViewAnyProject->value),
            default => false,
        };
    }

    public function ownsVendorContract(Contract $contract): bool
    {
        return $this->vendor_id !== null && $contract->vendor_id === $this->vendor_id;
    }

    public function ownsVendorInvoice(Invoice $invoice): bool
    {
        return $this->vendor_id !== null && $invoice->type->value === 'subcontractor' && $invoice->contract->vendor_id === $this->vendor_id;
    }

    /** @return BelongsToMany<Project, $this, ProjectAssignment> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectAssignment::class)
            ->withPivot(['project_role', 'can_view_financials', 'can_manage_documents'])
            ->withTimestamps();
    }

    /** @return HasMany<Contract, $this> */
    public function createdContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'created_by');
    }

    /** @return HasMany<ChangeOrder, $this> */
    public function createdChangeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class, 'created_by');
    }

    /** @return HasMany<Invoice, $this> */
    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    /** @return HasMany<Payment, $this> */
    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
