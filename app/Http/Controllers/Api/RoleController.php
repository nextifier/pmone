<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        // Only master users can manage roles
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master users can manage roles.',
            ], 403);
        }

        $query = Role::query()->with('permissions');
        $clientOnly = $request->boolean('client_only', false);

        // Apply filters and sorting only if not client-only mode
        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        // Paginate only if not client-only mode
        if ($clientOnly) {
            $roles = $query->get();

            return response()->json([
                'data' => $roles,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $roles->count(),
                    'total' => $roles->count(),
                ],
            ]);
        }

        $roles = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        // Only master users can manage roles
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master users can manage roles.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name', 'regex:/^[a-z0-9_-]+$/'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.regex' => 'Role name must be lowercase with only letters, numbers, hyphens, and underscores.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'web',
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();

            // Load permissions relationship
            $role->load('permissions');

            return response()->json([
                'message' => 'Role created successfully',
                'data' => $role,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request, string $name): JsonResponse
    {
        // Only master users can manage roles
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master users can manage roles.',
            ], 403);
        }

        $role = Role::where('name', $name)->with('permissions')->first();

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        return response()->json([
            'data' => $role,
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, string $name): JsonResponse
    {
        // Only master users can manage roles
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master users can manage roles.',
            ], 403);
        }

        $role = Role::where('name', $name)->first();

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        // Prevent editing master role
        if ($role->name === 'master') {
            return response()->json([
                'message' => 'Master role cannot be modified',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,'.$role->id, 'regex:/^[a-z0-9_-]+$/'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.regex' => 'Role name must be lowercase with only letters, numbers, hyphens, and underscores.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->has('name')) {
                $role->name = $request->name;
                $role->save();
            }

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();

            // Load permissions relationship
            $role->load('permissions');

            return response()->json([
                'message' => 'Role updated successfully',
                'data' => $role,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Request $request, string $name): JsonResponse
    {
        // Only master users can manage roles
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master users can manage roles.',
            ], 403);
        }

        $role = Role::where('name', $name)->first();

        if (! $role) {
            return response()->json([
                'message' => 'Role not found',
            ], 404);
        }

        // Prevent deleting master role
        if ($role->name === 'master') {
            return response()->json([
                'message' => 'Master role cannot be deleted',
            ], 403);
        }

        // Check if role is assigned to any users
        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        if ($usersCount > 0) {
            return response()->json([
                'message' => "Cannot delete role. It is assigned to {$usersCount} user(s).",
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Bulk delete roles.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        // Only master users can manage roles
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master users can manage roles.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:roles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $roles = Role::whereIn('id', $request->ids)->get();
        $deletedCount = 0;
        $errors = [];

        foreach ($roles as $role) {
            // Prevent deleting master role
            if ($role->name === 'master') {
                $errors[] = "Cannot delete master role";
                continue;
            }

            // Check if role is assigned to any users
            $usersCount = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->count();

            if ($usersCount > 0) {
                $errors[] = "Cannot delete {$role->name}. It is assigned to {$usersCount} user(s).";
                continue;
            }

            $role->delete();
            $deletedCount++;
        }

        return response()->json([
            'message' => "{$deletedCount} role(s) deleted successfully",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Get all permissions grouped by resource.
     */
    public function permissions(Request $request): JsonResponse
    {
        // Only master users can manage roles
        if (! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Unauthorized. Only master users can manage roles.',
            ], 403);
        }

        $allPermissions = Permission::orderBy('name')->get();
        $groupedPermissions = $this->groupPermissions($allPermissions);

        return response()->json([
            'data' => $groupedPermissions,
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
                'description' => 'Permissions not categorized in config',
                'type' => 'custom',
                'permissions' => $ungroupedPermissions->map(function ($permission) {
                    return [
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
