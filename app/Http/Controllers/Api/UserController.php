<?php

// app/Http/Controllers/Api/UserController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('users.view');

        $query = User::query()->with(['roles']);

        // Handle status filter (dots are converted to underscores by Nuxt/Nitro)
        if ($request->has('filter_status') && $request->input('filter_status')) {
            $statuses = explode(',', $request->input('filter_status'));
            $query->whereIn('status', $statuses);
        }

        // Handle search filter (dots are converted to underscores by Nuxt/Nitro)
        if ($request->has('filter_search') && $request->input('filter_search')) {
            $searchTerm = $request->input('filter_search');
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($searchTerm) . '%'])
                    ->orWhereRaw('LOWER(username) LIKE ?', ['%' . strtolower($searchTerm) . '%']);
            });
        }

        // Handle role filter (dots are converted to underscores by Nuxt/Nitro)
        if ($request->has('filter_role') && $request->input('filter_role')) {
            $roles = explode(',', $request->input('filter_role'));
            $query->whereHas('roles', function ($q) use ($roles) {
                $q->whereIn('name', $roles);
            });
        }

        // Handle verified filter (dots are converted to underscores by Nuxt/Nitro)
        if ($request->has('filter_verified') && $request->input('filter_verified')) {
            $verifiedStatuses = explode(',', $request->input('filter_verified'));
            $query->where(function ($q) use ($verifiedStatuses) {
                foreach ($verifiedStatuses as $status) {
                    if ($status === 'true') {
                        $q->orWhereNotNull('email_verified_at');
                    } elseif ($status === 'false') {
                        $q->orWhereNull('email_verified_at');
                    }
                }
            });
        }

        // Handle sorting
        $sortField = $request->input('sort', '-created_at');

        // Extract direction and field
        $direction = 'asc';
        $field = $sortField;
        if (str_starts_with($sortField, '-')) {
            $direction = 'desc';
            $field = substr($sortField, 1);
        }

        // Handle sorting by roles (relationship)
        if ($field === 'roles') {
            $query->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('users.*')
                ->groupBy('users.id')
                ->orderByRaw("MIN(roles.name) {$direction}");
        } elseif (in_array($field, ['name', 'email', 'username', 'status', 'email_verified_at', 'created_at', 'updated_at'])) {
            // Sort by actual database columns
            $query->orderBy($field, $direction);
        } else {
            // Default to created_at if invalid field
            $query->orderBy('created_at', 'desc');
        }

        $users = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('users.view');

        $user->load(['roles', 'media']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $userData = $request->validated();
            $userData['password'] = Hash::make($userData['password']);
            $userData['status'] = $userData['status'] ?? 'active';
            $userData['visibility'] = $userData['visibility'] ?? 'public';

            $user = User::create($userData);

            // Assign roles if provided, otherwise assign 'user' role
            $roles = $request->input('roles', ['user']);
            $user->assignRole($roles);

            $user->load(['roles', 'media']);

            return response()->json([
                'message' => 'User created successfully',
                'data' => new UserResource($user),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('User creation failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'permissions', 'oauthProviders', 'media']);

        return response()->json(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        // Prevent admin from editing master users
        if ($user->hasRole('master') && ! $request->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Only master users can edit other master users.',
            ], 403);
        }

        try {
            $userData = $request->validated();

            // Hash password if provided
            if (isset($userData['password'])) {
                $userData['password'] = Hash::make($userData['password']);
            }

            $user->update($userData);

            // Update roles if provided
            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles'));
            }

            $user->load(['roles', 'media']);

            return response()->json([
                'message' => 'User updated successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            logger()->error('User update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to update user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('users.delete');

        // Prevent admin from deleting master users
        if ($user->hasRole('master') && ! request()->user()->hasRole('master')) {
            return response()->json([
                'message' => 'Only master users can delete other master users.',
            ], 403);
        }

        try {
            // Soft delete or hard delete based on business logic
            // $user->update(['status' => 'inactive']);

            // Or for hard delete:
            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('User deletion failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to deactivate user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function showByUsername(User $user): JsonResponse
    {
        // Check if user profile is public or if current user has permission
        if ($user->visibility === 'private' && (! auth()->check() || auth()->id() !== $user->id)) {
            return response()->json([
                'message' => 'This profile is private',
            ], 403);
        }

        $user->load(['roles', 'media']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => ['required', 'array'],
            'settings.theme' => ['nullable', 'string', 'in:light,dark,system'],
            'settings.language' => ['nullable', 'string', 'max:5'],
            'settings.timezone' => ['nullable', 'string', 'max:50'],
            'settings.email_notifications' => ['nullable', 'boolean'],
            'settings.push_notifications' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $currentSettings = $user->user_settings ?? [];
            $newSettings = array_merge($currentSettings, $request->input('settings'));

            $user->update(['user_settings' => $newSettings]);

            return response()->json([
                'message' => 'Settings updated successfully',
                'settings' => $newSettings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateLinks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'links' => ['required', 'array'],
            'links.website' => ['nullable', 'url'],
            'links.twitter' => ['nullable', 'url'],
            'links.instagram' => ['nullable', 'url'],
            'links.linkedin' => ['nullable', 'url'],
            'links.github' => ['nullable', 'url'],
            'links.youtube' => ['nullable', 'url'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Filter out null values
            $links = array_filter($request->input('links'), function ($value) {
                return ! is_null($value) && $value !== '';
            });

            $user->update(['links' => $links]);

            return response()->json([
                'message' => 'Links updated successfully',
                'links' => $links,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update links',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function passwordStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'has_password' => ! is_null($user->password),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username,'.$user->id],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['sometimes', 'in:public,private'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user->update($validator->validated());

            $user->load(['roles', 'media']);

            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            logger()->error('Profile update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasPassword = ! is_null($user->password);

        // Define validation rules based on whether user has existing password
        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        // Only require current password if user has existing password
        if ($hasPassword) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        $validator = Validator::make($request->all(), $rules, [
            'current_password.current_password' => 'The current password is incorrect.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'message' => 'Password updated successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('Password update failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to update password',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRoles(): JsonResponse
    {
        $this->authorize('users.view');

        $user = request()->user();
        $query = Role::query()->select(['id', 'name']);

        // Only master users can see and assign master role
        if (! $user->hasRole('master')) {
            $query->where('name', '!=', 'master');
        }

        $roles = $query->get();

        return response()->json([
            'data' => $roles,
        ]);
    }
}
