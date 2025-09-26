<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
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

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $masterRole = Role::firstOrCreate(['name' => 'master']);
        $masterRole->syncPermissions(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions([
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'admin.view',
            'admin.settings',
            'admin.logs',
        ]);

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->syncPermissions([
            'users.view',
        ]);

        $writerRole = Role::firstOrCreate(['name' => 'writer']);
        $writerRole->syncPermissions([
            'users.view',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'user']);
        $userRole->syncPermissions([
            'users.view',
        ]);
    }
}
