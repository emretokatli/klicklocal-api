<?php

namespace Database\Seeders;

use App\Support\Permission;
use App\Support\PlatformRole;
use App\Support\TeamContext;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::platformPermissions() as $name) {
            PermissionModel::firstOrCreate([
                'name' => $name,
                'guard_name' => Permission::GUARD,
            ]);
        }

        foreach (Permission::workspacePermissions() as $name) {
            PermissionModel::firstOrCreate([
                'name' => $name,
                'guard_name' => Permission::GUARD,
            ]);
        }

        foreach (PlatformRole::permissionMap() as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => Permission::GUARD,
                'workspace_id' => TeamContext::PLATFORM,
            ]);
            $role->syncPermissions($permissions);
        }

        foreach (Permission::workspaceRolePermissionMap() as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => Permission::GUARD,
                'workspace_id' => TeamContext::PLATFORM,
            ]);
            $role->syncPermissions($permissions);
        }
    }
}
