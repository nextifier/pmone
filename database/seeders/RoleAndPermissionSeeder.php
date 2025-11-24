<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Default role permissions mapping.
     * Permissions are now dynamically generated from config/permissions.php.
     */
    private const ROLE_PERMISSIONS = [
        'master' => 'all', // Special case: gets all permissions
        'admin' => [
            // User management
            'users.create',
            'users.read',
            'users.update',
            'users.delete',
            // Role management
            'roles.create',
            'roles.read',
            'roles.update',
            'roles.delete',
            // Posts
            'posts.create',
            'posts.read',
            'posts.update',
            'posts.delete',
            // Projects
            'projects.create',
            'projects.read',
            'projects.update',
            'projects.delete',
            // Short Links
            'short_links.create',
            'short_links.read',
            'short_links.update',
            'short_links.delete',
            // Categories
            'categories.create',
            'categories.read',
            'categories.update',
            'categories.delete',
            // Tags
            'tags.create',
            'tags.read',
            'tags.update',
            'tags.delete',
            // Contact Forms
            'contact_forms.read',
            'contact_forms.update',
            'contact_forms.delete',
            // API Consumers
            'api_consumers.create',
            'api_consumers.read',
            'api_consumers.update',
            'api_consumers.delete',
            // Admin permissions
            'admin.view',
            'admin.settings',
            'admin.logs',
            // Analytics
            'analytics.view',
            'analytics.export',
        ],
        'staff' => [
            'users.read',
            'posts.read',
            'projects.read',
            'short_links.read',
            'categories.read',
            'tags.read',
            'contact_forms.read',
        ],
        'writer' => [
            'users.read',
            'posts.create',
            'posts.read',
            'posts.update',
            'posts.delete',
            'categories.read',
            'tags.read',
        ],
        'user' => [
            'users.read',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Sync permissions from config
        $this->syncPermissionsFromConfig();

        // Create roles with permissions
        $this->createRolesWithPermissions();
    }

    /**
     * Sync permissions from config/permissions.php to database.
     */
    private function syncPermissionsFromConfig(): void
    {
        $permissions = $this->getConfigPermissions();

        foreach ($permissions as $permissionName => $description) {
            Permission::firstOrCreate(
                ['name' => $permissionName],
                ['guard_name' => 'web']
            );
        }

        $this->command->info('Permissions synced from config.');
    }

    /**
     * Get all permissions from config with their descriptions.
     */
    private function getConfigPermissions(): array
    {
        $permissions = [];

        // Get resource-based permissions (CRUD)
        $resources = config('permissions.resources', []);
        $actionLabels = config('permissions.action_labels', []);

        foreach ($resources as $resource => $config) {
            $label = $config['label'] ?? ucfirst($resource);
            $actions = $config['actions'] ?? ['create', 'read', 'update', 'delete'];

            foreach ($actions as $action) {
                $permissionName = "{$resource}.{$action}";
                $actionLabel = $actionLabels[$action] ?? ucfirst($action);
                $permissions[$permissionName] = "{$actionLabel} {$label}";
            }
        }

        // Get custom permissions
        $customGroups = config('permissions.custom', []);

        foreach ($customGroups as $group => $config) {
            $customPermissions = $config['permissions'] ?? [];

            foreach ($customPermissions as $permissionName => $description) {
                $permissions[$permissionName] = $description;
            }
        }

        return $permissions;
    }

    /**
     * Create roles and assign permissions.
     */
    private function createRolesWithPermissions(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if ($permissions === 'all') {
                // Master role gets all permissions
                $role->syncPermissions(Permission::all());
            } else {
                // Filter to only assign permissions that exist in database
                $existingPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
                $role->syncPermissions($existingPermissions);
            }

            $this->command->info("Role '{$roleName}' created/updated with permissions.");
        }
    }
}

