<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRolesPermissionsRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSyncController extends Controller
{
    use AuthorizesRequests;

    /**
     * Export all permissions and roles with their permission mappings.
     */
    public function export(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('roles.read')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to export roles.',
            ], 403);
        }

        $permissions = Permission::orderBy('name')->pluck('name')->values()->toArray();

        $roles = Role::with('permissions')->orderBy('name')->get();

        $rolesData = [];
        foreach ($roles as $role) {
            $rolesData[$role->name] = $role->permissions->pluck('name')->sort()->values()->toArray();
        }

        return response()->json([
            'data' => [
                'permissions' => $permissions,
                'roles' => $rolesData,
            ],
        ]);
    }

    /**
     * Import permissions and roles (preview or apply).
     */
    public function import(ImportRolesPermissionsRequest $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('roles.update')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to import roles.',
            ], 403);
        }

        $validated = $request->validated();
        $preview = $request->boolean('preview', true);

        $incomingPermissions = collect($validated['permissions']);
        $incomingRoles = collect($validated['roles']);

        $existingPermissions = Permission::pluck('name');
        $existingRoles = Role::with('permissions')->get()->keyBy('name');

        // Calculate diff
        $permissionsToCreate = $incomingPermissions->diff($existingPermissions)->values()->toArray();
        $rolesToCreate = $incomingRoles->keys()->diff($existingRoles->keys())->values()->toArray();

        $rolesToSync = [];
        foreach ($incomingRoles as $roleName => $rolePermissions) {
            $currentPermissions = $existingRoles->has($roleName)
                ? $existingRoles[$roleName]->permissions->pluck('name')->toArray()
                : [];

            $added = array_values(array_diff($rolePermissions, $currentPermissions));
            $removed = array_values(array_diff($currentPermissions, $rolePermissions));

            if (count($added) > 0 || count($removed) > 0) {
                $rolesToSync[$roleName] = [
                    'added' => $added,
                    'removed' => $removed,
                ];
            }
        }

        if ($preview) {
            return response()->json([
                'data' => [
                    'permissions_to_create' => $permissionsToCreate,
                    'roles_to_create' => $rolesToCreate,
                    'roles_to_sync' => $rolesToSync,
                ],
            ]);
        }

        // Apply changes
        DB::beginTransaction();

        try {
            // Create missing permissions
            foreach ($permissionsToCreate as $permissionName) {
                Permission::create([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
            }

            // Create missing roles
            foreach ($rolesToCreate as $roleName) {
                Role::create([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);
            }

            // Sync permissions for each role
            foreach ($incomingRoles as $roleName => $rolePermissions) {
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $role->syncPermissions($rolePermissions);
                }
            }

            // Clear permission cache
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            DB::commit();

            activity()
                ->causedBy($request->user())
                ->event('roles_permissions_imported')
                ->withProperties([
                    'permissions_created' => $permissionsToCreate,
                    'roles_created' => $rolesToCreate,
                    'roles_synced' => array_keys($rolesToSync),
                ])
                ->log('Imported roles and permissions');

            return response()->json([
                'message' => 'Import completed successfully',
                'data' => [
                    'permissions_created' => count($permissionsToCreate),
                    'roles_created' => count($rolesToCreate),
                    'roles_synced' => count($rolesToSync),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to import roles and permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
