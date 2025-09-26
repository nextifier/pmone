<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        // User management
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',

        // System administration
        'admin.view',
        'admin.settings',
        'admin.logs',
    ];

    private const ROLE_PERMISSIONS = [
        'master' => 'all', // Special case: gets all permissions
        'admin' => [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'admin.view',
            'admin.settings',
            'admin.logs',
        ],
        'staff' => [
            'users.view',
        ],
        'writer' => [
            'users.view',
        ],
        'user' => [
            'users.view',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRolesWithPermissions();
    }

    private function createPermissions(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }

    private function createRolesWithPermissions(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if ($permissions === 'all') {
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($permissions);
            }
        }
    }
}
