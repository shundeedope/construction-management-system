<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Roles are assigned to the demo users by PeopleSeeder, which owns the accounts.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        // syncPermissions and syncRoles keep re-runs idempotent, and also prune any capability
        // that has since been removed from the matrix in App\Enums\Role.
        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->syncPermissions($role->permissionNames());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
