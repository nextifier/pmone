<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of permissions.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('permissions.read')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to view permissions.',
            ], 403);
        }

        $query = Permission::query();
        $clientOnly = $request->boolean('client_only', false);

        // Apply filters and sorting only if not client-only mode
        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        // Paginate only if not client-only mode
        if ($clientOnly) {
            $permissions = $query->orderBy('name')->get();

            return response()->json([
                'data' => $permissions,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $permissions->count(),
                    'total' => $permissions->count(),
                ],
            ]);
        }

        $permissions = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $permissions->items(),
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
        ]);
    }

    /**
     * Get all permissions grouped by resource.
     */
    public function grouped(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('permissions.read')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to view permissions.',
            ], 403);
        }

        $allPermissions = Permission::orderBy('name')->get();
        $groupedPermissions = $this->groupPermissions($allPermissions);

        return response()->json([
            'data' => $groupedPermissions,
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('permissions.create')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to create permissions.',
            ], 403);
        }

        // Convert permission name to slug format with dots
        $permissionName = Str::slug($request->name, '.');

        $validator = Validator::make(
            ['name' => $permissionName, 'description' => $request->description, 'group' => $request->group],
            [
                'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
                'description' => ['nullable', 'string', 'max:500'],
                'group' => ['nullable', 'string', 'max:100'],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $permission = Permission::create([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Permission created successfully',
                'data' => $permission,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create permission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified permission.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('permissions.read')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to view permissions.',
            ], 403);
        }

        $permission = Permission::with('roles')->find($id);

        if (! $permission) {
            return response()->json([
                'message' => 'Permission not found',
            ], 404);
        }

        return response()->json([
            'data' => $permission,
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('permissions.update')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to update permissions.',
            ], 403);
        }

        $permission = Permission::find($id);

        if (! $permission) {
            return response()->json([
                'message' => 'Permission not found',
            ], 404);
        }

        // Convert permission name to slug format if provided
        $permissionName = $request->has('name') ? Str::slug($request->name, '.') : null;

        $validationData = array_filter([
            'name' => $permissionName,
            'description' => $request->description,
            'group' => $request->group,
        ], fn ($value) => $value !== null);

        $validator = Validator::make($validationData, [
            'name' => ['sometimes', 'string', 'max:255', 'unique:permissions,name,'.$permission->id],
            'description' => ['nullable', 'string', 'max:500'],
            'group' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($permissionName) {
                $permission->name = $permissionName;
            }
            $permission->save();

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            DB::commit();

            return response()->json([
                'message' => 'Permission updated successfully',
                'data' => $permission,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update permission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('permissions.delete')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to delete permissions.',
            ], 403);
        }

        $permission = Permission::find($id);

        if (! $permission) {
            return response()->json([
                'message' => 'Permission not found',
            ], 404);
        }

        // Check if permission is assigned to any roles
        $rolesCount = $permission->roles()->count();

        if ($rolesCount > 0) {
            return response()->json([
                'message' => "Cannot delete permission. It is assigned to {$rolesCount} role(s). Please remove it from all roles first.",
            ], 422);
        }

        $permission->delete();

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * Bulk delete permissions.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('permissions.delete')) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to delete permissions.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $permissions = Permission::whereIn('id', $request->ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($permissions as $permission) {
            // Check if permission is assigned to any roles
            $rolesCount = $permission->roles()->count();

            if ($rolesCount > 0) {
                $errors[] = "Cannot delete {$permission->name}. It is assigned to {$rolesCount} role(s).";

                continue;
            }

            $permission->delete();
            $deletedCount++;
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => "{$deletedCount} permission(s) deleted successfully",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Group permissions by resource based on config.
     */
    protected function groupPermissions($permissions): array
    {
        $grouped = [];
        $resources = config('permissions.resources', []);
        $customGroups = config('permissions.custom', []);

        // Group resource-based permissions
        foreach ($resources as $resource => $config) {
            $label = $config['label'] ?? ucfirst($resource);
            $description = $config['description'] ?? '';
            $actions = $config['actions'] ?? ['create', 'read', 'update', 'delete'];

            $resourcePermissions = [];
            foreach ($actions as $action) {
                $permissionName = "{$resource}.{$action}";
                $permission = $permissions->firstWhere('name', $permissionName);

                if ($permission) {
                    $resourcePermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'action' => $action,
                    ];
                }
            }

            if (count($resourcePermissions) > 0) {
                $grouped[] = [
                    'group' => $resource,
                    'label' => $label,
                    'description' => $description,
                    'type' => 'resource',
                    'permissions' => $resourcePermissions,
                ];
            }
        }

        // Group custom permissions
        foreach ($customGroups as $group => $config) {
            $label = $config['label'] ?? ucfirst($group);
            $description = $config['description'] ?? '';
            $customPermissionsList = $config['permissions'] ?? [];

            $groupPermissions = [];
            foreach ($customPermissionsList as $permissionName => $permissionDescription) {
                $permission = $permissions->firstWhere('name', $permissionName);

                if ($permission) {
                    $groupPermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'description' => $permissionDescription,
                    ];
                }
            }

            if (count($groupPermissions) > 0) {
                $grouped[] = [
                    'group' => $group,
                    'label' => $label,
                    'description' => $description,
                    'type' => 'custom',
                    'permissions' => $groupPermissions,
                ];
            }
        }

        // Group ungrouped permissions (permissions not in config)
        $allConfigPermissionNames = [];

        foreach ($resources as $resource => $config) {
            $actions = $config['actions'] ?? ['create', 'read', 'update', 'delete'];
            foreach ($actions as $action) {
                $allConfigPermissionNames[] = "{$resource}.{$action}";
            }
        }

        foreach ($customGroups as $config) {
            $customPermissionsList = $config['permissions'] ?? [];
            $allConfigPermissionNames = array_merge($allConfigPermissionNames, array_keys($customPermissionsList));
        }

        $ungroupedPermissions = $permissions->filter(function ($permission) use ($allConfigPermissionNames) {
            return ! in_array($permission->name, $allConfigPermissionNames);
        });

        if ($ungroupedPermissions->count() > 0) {
            $grouped[] = [
                'group' => 'other',
                'label' => 'Other Permissions',
                'description' => 'Custom permissions created dynamically',
                'type' => 'custom',
                'permissions' => $ungroupedPermissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'description' => $permission->name,
                    ];
                })->values()->toArray(),
            ];
        }

        return $grouped;
    }

    /**
     * Apply filters to query.
     */
    protected function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($request->filled('filter.search')) {
            $search = $request->input('filter.search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Group filter (by prefix before first dot)
        if ($request->filled('filter.group')) {
            $group = $request->input('filter.group');
            $query->where('name', 'like', "{$group}.%");
        }
    }

    /**
     * Apply sorting to query.
     */
    protected function applySorting($query, Request $request): void
    {
        $sort = $request->input('sort', 'name');
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = substr($sort, 1);
        }

        $allowedSorts = ['name', 'created_at'];

        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        }
    }
}
