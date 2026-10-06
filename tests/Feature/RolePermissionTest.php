<?php

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('seeds every role and capability', function () {
    expect(RoleModel::query()->count())->toBe(count(Role::cases()))
        ->and(PermissionModel::query()->count())->toBe(count(Permission::cases()));

    foreach (Role::cases() as $role) {
        expect(RoleModel::findByName($role->value, 'web'))->not->toBeNull();
    }
});

it('grants each role exactly its declared capability matrix', function (Role $role) {
    $granted = RoleModel::findByName($role->value, 'web')
        ->permissions
        ->pluck('name')
        ->all();

    expect($granted)->toEqualCanonicalizing($role->permissionNames());
})->with(fn () => array_map(fn (Role $role): array => [$role], Role::cases()));

it('gives the administrator every capability', function () {
    $admin = userWithRole(Role::Administrator);

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue("Administrator must hold {$permission->value}");
    }
});

it('makes the executive strictly read only', function () {
    $executive = userWithRole(Role::Executive);

    $mutating = [
        Permission::CreateProject, Permission::UpdateProject, Permission::DeleteProject,
        Permission::ManageProjectTeam, Permission::ManageOrganizations, Permission::ManageContracts,
        Permission::ExecuteContracts, Permission::ManageChangeOrders, Permission::ApproveChangeOrders,
        Permission::ManageInvoices, Permission::ApproveInvoices, Permission::VoidInvoices,
        Permission::ManagePayments, Permission::ManageDocuments, Permission::DeleteDocuments,
        Permission::ApproveTimeEntries, Permission::ManageLeave, Permission::ManageEmployees,
        Permission::ManageCompliance, Permission::VerifyCompliance, Permission::ManageUsers,
        Permission::ManageSettings,
    ];

    foreach ($mutating as $permission) {
        expect($executive->can($permission->value))->toBeFalse("Executive must not hold {$permission->value}");
    }

    expect($executive->can(Permission::ViewAllProjects->value))->toBeTrue()
        ->and($executive->can(Permission::ViewAllFinancials->value))->toBeTrue();
});

it('lets accounting run the money without gaining operational project edits', function () {
    $accounting = userWithRole(Role::Accounting);

    expect($accounting->can(Permission::ManageInvoices->value))->toBeTrue()
        ->and($accounting->can(Permission::ApproveInvoices->value))->toBeTrue()
        ->and($accounting->can(Permission::VoidInvoices->value))->toBeTrue()
        ->and($accounting->can(Permission::ManagePayments->value))->toBeTrue()
        ->and($accounting->can(Permission::ViewAllFinancials->value))->toBeTrue();

    expect($accounting->can(Permission::CreateProject->value))->toBeFalse()
        ->and($accounting->can(Permission::UpdateProject->value))->toBeFalse()
        ->and($accounting->can(Permission::DeleteProject->value))->toBeFalse()
        ->and($accounting->can(Permission::ManageProjectTeam->value))->toBeFalse()
        ->and($accounting->can(Permission::ApproveTimeEntries->value))->toBeFalse();
});

it('withholds cross-project visibility from operational roles', function (Role $role) {
    $user = userWithRole($role);

    expect($user->can(Permission::ViewAllProjects->value))->toBeFalse()
        ->and($user->can(Permission::ViewAllFinancials->value))->toBeFalse();
})->with([
    'project manager' => [Role::ProjectManager],
    'site supervisor' => [Role::SiteSupervisor],
    'employee' => [Role::Employee],
]);

it('withholds financial capability from employees entirely', function () {
    $employee = userWithRole(Role::Employee);

    expect($employee->can(Permission::ViewFinancials->value))->toBeFalse()
        ->and($employee->can(Permission::ViewAllFinancials->value))->toBeFalse()
        ->and($employee->can(Permission::ManageInvoices->value))->toBeFalse();
});

it('gives approval capability only to the roles that own each workflow', function () {
    $manager = userWithRole(Role::ProjectManager);
    $accounting = userWithRole(Role::Accounting);
    $supervisor = userWithRole(Role::SiteSupervisor);

    // Change orders and contract execution belong to project management.
    expect($manager->can(Permission::ApproveChangeOrders->value))->toBeTrue()
        ->and($manager->can(Permission::ExecuteContracts->value))->toBeTrue()
        ->and($accounting->can(Permission::ApproveChangeOrders->value))->toBeFalse()
        ->and($accounting->can(Permission::ExecuteContracts->value))->toBeFalse();

    // Invoice approval and payment belong to accounting.
    expect($manager->can(Permission::ApproveInvoices->value))->toBeFalse()
        ->and($manager->can(Permission::ManagePayments->value))->toBeFalse();

    // Timesheet approval belongs to the field hierarchy.
    expect($supervisor->can(Permission::ApproveTimeEntries->value))->toBeTrue()
        ->and($manager->can(Permission::ApproveTimeEntries->value))->toBeTrue()
        ->and($accounting->can(Permission::ApproveTimeEntries->value))->toBeFalse();
});

it('restricts administration to administrators', function () {
    foreach ([Role::ProjectManager, Role::Accounting, Role::SiteSupervisor, Role::Employee, Role::Executive] as $role) {
        $user = userWithRole($role);

        expect($user->can(Permission::ManageUsers->value))->toBeFalse()
            ->and($user->can(Permission::ManageSettings->value))->toBeFalse();
    }
});

it('is idempotent when the seeder runs twice', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::query()->count())->toBe(count(Role::cases()))
        ->and(PermissionModel::query()->count())->toBe(count(Permission::cases()))
        ->and(RoleModel::findByName(Role::Accounting->value, 'web')->permissions)
        ->toHaveCount(count(Role::Accounting->permissions()));
});
