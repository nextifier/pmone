<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'permissions:sync
                            {--dry-run : Display permissions that would be synced without making changes}
                            {--prune : Remove permissions that are no longer defined in config}';

    /**
     * The console command description.
     */
    protected $description = 'Sync permissions from config/permissions.php to database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $shouldPrune = $this->option('prune');

        $this->info('Starting permission synchronization...');
        $this->newLine();

        // Get all permissions from config
        $configPermissions = $this->getConfigPermissions();
        $existingPermissions = Permission::pluck('name')->toArray();

        // Track statistics
        $created = 0;
        $unchanged = 0;
        $pruned = 0;

        // Create or update permissions from config
        foreach ($configPermissions as $permissionName => $description) {
            if (in_array($permissionName, $existingPermissions)) {
                $unchanged++;
                continue;
            }

            if ($isDryRun) {
                $this->line("  <fg=yellow>Would create:</> {$permissionName} - {$description}");
                $created++;
            } else {
                Permission::create([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $this->line("  <fg=green>Created:</> {$permissionName} - {$description}");
                $created++;
            }
        }

        // Prune permissions that are no longer in config
        if ($shouldPrune) {
            $configPermissionNames = array_keys($configPermissions);
            $permissionsToPrune = array_diff($existingPermissions, $configPermissionNames);

            foreach ($permissionsToPrune as $permissionName) {
                if ($isDryRun) {
                    $this->line("  <fg=red>Would delete:</> {$permissionName}");
                    $pruned++;
                } else {
                    Permission::where('name', $permissionName)->delete();
                    $this->line("  <fg=red>Deleted:</> {$permissionName}");
                    $pruned++;
                }
            }
        }

        // Display summary
        $this->newLine();
        $this->info('Synchronization complete!');
        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $created],
                ['Unchanged', $unchanged],
                ['Pruned', $pruned],
            ]
        );

        if ($isDryRun) {
            $this->warn('This was a dry run. No changes were made.');
            $this->info('Run without --dry-run to apply changes.');
        }

        // Clear permission cache
        if (!$isDryRun && ($created > 0 || $pruned > 0)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->info('Permission cache cleared.');
        }

        return self::SUCCESS;
    }

    /**
     * Get all permissions from config with their descriptions.
     */
    protected function getConfigPermissions(): array
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
}
