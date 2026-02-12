<?php

// app/Http/Controllers/Api/UserController.php

namespace App\Http\Controllers\Api;

use App\Exports\UsersExport;
use App\Exports\UsersTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserIndexResource;
use App\Http\Resources\UserResource;
use App\Imports\UsersImport;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('users.read');

        $query = User::query()->with(['roles', 'creator', 'updater'])->withCount('posts');
        $clientOnly = $request->boolean('client_only', false);

        // Apply filters and sorting only if not client-only mode
        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        // Paginate only if not client-only mode
        if ($clientOnly) {
            $users = $query->orderByRaw('last_seen IS NULL')->orderByDesc('last_seen')->get();

            return response()->json([
                'data' => UserIndexResource::collection($users),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $users->count(),
                    'total' => $users->count(),
                ],
            ]);
        }

        $users = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => UserIndexResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('users.read');

        $query = User::onlyTrashed()->with(['roles', 'deleter']);
        $clientOnly = $request->boolean('client_only', false);

        // Apply filters and sorting only if not client-only mode
        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        // Paginate only if not client-only mode
        if ($clientOnly) {
            $users = $query->get();

            return response()->json([
                'data' => UserIndexResource::collection($users),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $users->count(),
                    'total' => $users->count(),
                ],
            ]);
        }

        $users = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => UserIndexResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        // Search filter
        if ($searchTerm = $request->input('filter_search')) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTerm = strtolower($searchTerm);
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Status filter
        if ($statuses = $request->input('filter_status')) {
            $query->whereIn('status', explode(',', $statuses));
        }

        // Role filter
        if ($roles = $request->input('filter_role')) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', explode(',', $roles)));
        }

        // Verified filter
        if ($verifiedStatuses = $request->input('filter_verified')) {
            $query->where(function ($q) use ($verifiedStatuses) {
                $statuses = explode(',', $verifiedStatuses);
                if (in_array('true', $statuses)) {
                    $q->orWhereNotNull('email_verified_at');
                }
                if (in_array('false', $statuses)) {
                    $q->orWhereNull('email_verified_at');
                }
            });
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortField = $request->input('sort', '-last_seen');
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if ($field === 'roles') {
            $query->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('users.*')
                ->groupBy('users.id')
                ->orderByRaw("MIN(roles.name) {$direction}");
        } elseif ($field === 'last_seen') {
            $query->orderByRaw("last_seen IS NULL")
                ->orderBy('last_seen', $direction);
        } elseif (in_array($field, ['name', 'email', 'username', 'status', 'email_verified_at', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderByRaw("last_seen IS NULL")
                ->orderBy('last_seen', 'desc');
        }
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('users.read');

        $user->load(['roles', 'media', 'links']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $userData = $request->validated();
                $linksData = $userData['links'] ?? [];
                unset($userData['links']);

                // Auto-generate name from email if not provided
                if (empty($userData['name'])) {
                    $userData['name'] = explode('@', $userData['email'])[0];
                }

                // Hash password only if provided
                if (! empty($userData['password'])) {
                    $userData['password'] = Hash::make($userData['password']);
                } else {
                    unset($userData['password']);
                }

                $userData['status'] = $userData['status'] ?? 'active';
                $userData['visibility'] = $userData['visibility'] ?? 'public';

                $user = User::create($userData);

                // Assign roles if provided, otherwise assign 'user' role
                $roles = $request->input('roles', ['user']);
                $user->assignRole($roles);

                // Create links if provided
                if (! empty($linksData)) {
                    foreach ($linksData as $index => $linkData) {
                        $user->links()->create([
                            'label' => $linkData['label'],
                            'url' => $linkData['url'],
                            'order' => $index,
                            'is_active' => true,
                        ]);
                    }
                }

                // Auto-create Email and WhatsApp links
                \App\Helpers\LinkSyncHelper::syncUserContactLinks($user);

                // Handle profile image upload from temporary storage
                $this->handleTemporaryUpload($request, $user, 'tmp_profile_image', 'profile_image');

                // Handle cover image upload from temporary storage
                $this->handleTemporaryUpload($request, $user, 'tmp_cover_image', 'cover_image');

                $user->load(['roles', 'media', 'links']);

                return response()->json([
                    'message' => 'User created successfully',
                    'data' => new UserResource($user),
                ], 201);
            } catch (\Illuminate\Database\QueryException $e) {
                // Check if it's a duplicate username error (PostgreSQL error code 23505)
                if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'users_username_unique')) {
                    $attempt++;

                    if ($attempt >= $maxRetries) {
                        logger()->error('User creation failed after retries - duplicate username', [
                            'error' => $e->getMessage(),
                            'data' => $request->except(['password', 'tmp_upload_folder']),
                            'attempts' => $attempt,
                        ]);

                        return response()->json([
                            'message' => 'Failed to create user. Please try with a different username or let the system generate one.',
                            'error' => 'The generated username is already taken. Please try again.',
                        ], 422);
                    }

                    // Wait a short moment before retry to reduce collision chance
                    usleep(50000 * $attempt); // 50ms, 100ms, 150ms

                    continue;
                }

                // Re-throw if it's not a duplicate username error
                throw $e;
            } catch (\Exception $e) {
                logger()->error('User creation failed', [
                    'error' => $e->getMessage(),
                    'data' => $request->except(['password', 'tmp_upload_folder']),
                ]);

                return response()->json([
                    'message' => 'Failed to create user',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // This should never be reached, but just in case
        return response()->json([
            'message' => 'Failed to create user after multiple attempts',
        ], 500);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'permissions', 'oauthProviders', 'media', 'links']);

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
            $linksData = $userData['links'] ?? null;
            unset($userData['links']);

            // Hash password if provided
            if (isset($userData['password'])) {
                $userData['password'] = Hash::make($userData['password']);
            }

            $user->update($userData);

            // Update roles if provided
            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles'));
            }

            // Handle links update if provided
            if ($linksData !== null) {
                // Delete all existing links EXCEPT Email and WhatsApp
                $user->links()->where(function ($query) {
                    $query->where('label', '!=', 'Email')
                        ->where('label', '!=', 'WhatsApp')
                        ->where('label', 'NOT LIKE', 'WhatsApp %');
                })->delete();

                // Create new links with order (skip Email/WhatsApp from form)
                foreach ($linksData as $index => $linkData) {
                    // Skip if trying to create Email or WhatsApp link manually
                    if (\App\Helpers\LinkSyncHelper::isContactLink($linkData['label'])) {
                        continue;
                    }

                    $user->links()->create([
                        'label' => $linkData['label'],
                        'url' => $linkData['url'],
                        'order' => $index,
                        'is_active' => true,
                    ]);
                }
            }

            // Auto-sync Email and WhatsApp links
            \App\Helpers\LinkSyncHelper::syncUserContactLinks($user);

            // Handle profile image upload from temporary storage
            $this->handleTemporaryUpload($request, $user, 'tmp_profile_image', 'profile_image');

            // Handle cover image upload from temporary storage
            $this->handleTemporaryUpload($request, $user, 'tmp_cover_image', 'cover_image');

            $user->load(['roles', 'media', 'links']);

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

    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize('users.delete');

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userIds = $request->input('ids');
            $currentUser = $request->user();
            $deletedCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if (! $user) {
                    continue;
                }

                // Prevent admin from deleting master users
                if ($user->hasRole('master') && ! $currentUser->hasRole('master')) {
                    $errors[] = "Cannot delete master user: {$user->name}";

                    continue;
                }

                // Prevent self-deletion
                if ($user->id === $currentUser->id) {
                    $errors[] = 'Cannot delete your own account';

                    continue;
                }

                $user->delete();
                $deletedCount++;
            }

            $message = $deletedCount > 0
                ? "Successfully deleted {$deletedCount} user(s)"
                : 'No users were deleted';

            return response()->json([
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ], $deletedCount > 0 ? 200 : 400);
        } catch (\Exception $e) {
            logger()->error('Bulk user deletion failed', [
                'error' => $e->getMessage(),
                'user_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to delete users',
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

        $user->load(['roles', 'media', 'links']);

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
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'url', 'max:500'],
        ], [
            'links.*.label.required' => 'Link label is required.',
            'links.*.label.max' => 'Link label must not exceed 100 characters.',
            'links.*.url.required' => 'Link URL is required.',
            'links.*.url.url' => 'Please enter a valid URL.',
            'links.*.url.max' => 'Link URL must not exceed 500 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $linksData = $request->input('links', []);

            // Delete all existing links EXCEPT Email and WhatsApp
            $user->links()->where(function ($query) {
                $query->where('label', '!=', 'Email')
                    ->where('label', '!=', 'WhatsApp')
                    ->where('label', 'NOT LIKE', 'WhatsApp %');
            })->delete();

            // Create new links with order (skip Email/WhatsApp from form)
            foreach ($linksData as $index => $linkData) {
                // Skip if trying to create Email or WhatsApp link manually
                if (\App\Helpers\LinkSyncHelper::isContactLink($linkData['label'])) {
                    continue;
                }

                $user->links()->create([
                    'label' => $linkData['label'],
                    'url' => $linkData['url'],
                    'order' => $index,
                    'is_active' => true,
                ]);
            }

            // Auto-sync Email and WhatsApp links
            \App\Helpers\LinkSyncHelper::syncUserContactLinks($user);

            // Load fresh links
            $user->load('links');

            return response()->json([
                'message' => 'Links updated successfully',
                'data' => $user->links,
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
            'title' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'url', 'max:500'],
            'visibility' => ['sometimes', 'in:public,private'],
            'tmp_profile_image' => ['nullable', 'string'],
            'tmp_cover_image' => ['nullable', 'string'],
            'delete_profile_image' => ['nullable', 'boolean'],
            'delete_cover_image' => ['nullable', 'boolean'],
        ], [
            'links.*.label.required' => 'Link label is required.',
            'links.*.label.max' => 'Link label must not exceed 100 characters.',
            'links.*.url.required' => 'Link URL is required.',
            'links.*.url.url' => 'Please enter a valid URL.',
            'links.*.url.max' => 'Link URL must not exceed 500 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Extract links data before update
            $validatedData = $validator->validated();
            $linksData = $validatedData['links'] ?? null;
            unset($validatedData['links']);

            // Update user profile
            $user->update($validatedData);

            // Handle links update if provided
            if ($linksData !== null) {
                // Delete all existing links EXCEPT Email and WhatsApp
                $user->links()->where(function ($query) {
                    $query->where('label', '!=', 'Email')
                        ->where('label', '!=', 'WhatsApp')
                        ->where('label', 'NOT LIKE', 'WhatsApp %');
                })->delete();

                // Create new links with order (skip Email/WhatsApp from form)
                foreach ($linksData as $index => $linkData) {
                    // Skip if trying to create Email or WhatsApp link manually
                    if (\App\Helpers\LinkSyncHelper::isContactLink($linkData['label'])) {
                        continue;
                    }

                    $user->links()->create([
                        'label' => $linkData['label'],
                        'url' => $linkData['url'],
                        'order' => $index,
                        'is_active' => true,
                    ]);
                }
            }

            // Auto-sync Email and WhatsApp links
            \App\Helpers\LinkSyncHelper::syncUserContactLinks($user);

            // Handle profile image upload from temporary storage
            $this->handleTemporaryUpload($request, $user, 'tmp_profile_image', 'profile_image');

            // Handle cover image upload from temporary storage
            $this->handleTemporaryUpload($request, $user, 'tmp_cover_image', 'cover_image');

            $user->load(['roles', 'media', 'links']);

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
        $this->authorize('users.read');

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

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('users.read');

        // Get filters and sorting from request
        // Note: Laravel converts dots in query params to underscores
        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }
        if ($status = $request->input('filter_status')) {
            $filters['status'] = $status;
        }
        if ($role = $request->input('filter_role')) {
            $filters['role'] = $role;
        }
        if ($verified = $request->input('filter_verified')) {
            $filters['verified'] = $verified;
        }

        $sort = $request->input('sort', '-created_at');

        // Create the export with filters and sorting
        $export = new UsersExport($filters, $sort);

        // Generate filename with timestamp
        $filename = 'users_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('users.create');

        $filename = 'users_import_template.xlsx';

        return Excel::download(new UsersTemplateExport, $filename);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('users.create');

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tempFolder = null;

        try {
            $tempFolder = $request->input('file');

            // Get file path from temporary storage
            $metadataPath = "tmp/uploads/{$tempFolder}/metadata.json";

            if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($metadataPath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            $metadata = json_decode(
                \Illuminate\Support\Facades\Storage::disk('local')->get($metadataPath),
                true
            );

            $filePath = "tmp/uploads/{$tempFolder}/{$metadata['original_name']}";

            if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            // Import users
            $import = new UsersImport;
            Excel::import($import, \Illuminate\Support\Facades\Storage::disk('local')->path($filePath));

            // Get import results
            $failures = $import->getFailures();
            $importedCount = $import->getImportedCount();
            $errorMessages = [];

            foreach ($failures as $failure) {
                $errorMessages[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            if (count($errorMessages) > 0) {
                return response()->json([
                    'message' => 'Import completed with errors',
                    'errors' => $errorMessages,
                    'imported_count' => $importedCount,
                ], 422);
            }

            return response()->json([
                'message' => 'Users imported successfully',
                'imported_count' => $importedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('User import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import users',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            // Always clean up temporary files
            if ($tempFolder) {
                \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("tmp/uploads/{$tempFolder}");
            }
        }
    }

    /**
     * Handle temporary file upload and move to media collection.
     */
    private function handleTemporaryUpload(Request $request, User $user, string $fieldName, string $collection): void
    {
        // Check for delete flag first
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $user->clearMediaCollection($collection);

            return;
        }

        // If field is not present, do nothing (keep existing media)
        if (! $request->has($fieldName)) {
            return;
        }

        $value = $request->input($fieldName);

        // If value is null/empty, skip (already handled by delete flag above)
        if (! $value) {
            return;
        }

        // If value doesn't start with 'tmp-', it's an existing media URL, skip
        if (! \Illuminate\Support\Str::startsWith($value, 'tmp-')) {
            return;
        }

        // Handle new upload from temporary storage
        $metadataPath = "tmp/uploads/{$value}/metadata.json";

        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(
            \Illuminate\Support\Facades\Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
            return;
        }

        // Clear existing media in this collection first
        $user->clearMediaCollection($collection);

        // Add new media
        $user->addMedia(\Illuminate\Support\Facades\Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        // Clean up temporary files
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorize('users.delete');

        try {
            $user = User::onlyTrashed()->findOrFail($id);

            // Prevent admin from restoring master users
            if ($user->hasRole('master') && ! $request->user()->hasRole('master')) {
                return response()->json([
                    'message' => 'Only master users can restore other master users.',
                ], 403);
            }

            $user->restore();

            return response()->json([
                'message' => 'User restored successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('User restoration failed', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to restore user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $this->authorize('users.delete');

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userIds = $request->input('ids');
            $currentUser = $request->user();
            $restoredCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                $user = User::onlyTrashed()->find($userId);

                if (! $user) {
                    continue;
                }

                // Prevent admin from restoring master users
                if ($user->hasRole('master') && ! $currentUser->hasRole('master')) {
                    $errors[] = "Cannot restore master user: {$user->name}";

                    continue;
                }

                $user->restore();
                $restoredCount++;
            }

            $message = $restoredCount > 0
                ? "Successfully restored {$restoredCount} user(s)"
                : 'No users were restored';

            return response()->json([
                'message' => $message,
                'restored_count' => $restoredCount,
                'errors' => $errors,
            ], $restoredCount > 0 ? 200 : 400);
        } catch (\Exception $e) {
            logger()->error('Bulk user restoration failed', [
                'error' => $e->getMessage(),
                'user_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to restore users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function forceDestroy(Request $request, int $id): JsonResponse
    {
        $this->authorize('users.delete');

        try {
            $user = User::onlyTrashed()->findOrFail($id);

            // Prevent admin from permanently deleting master users
            if ($user->hasRole('master') && ! $request->user()->hasRole('master')) {
                return response()->json([
                    'message' => 'Only master users can permanently delete other master users.',
                ], 403);
            }

            $user->forceDelete();

            return response()->json([
                'message' => 'User permanently deleted successfully',
            ]);
        } catch (\Exception $e) {
            logger()->error('User permanent deletion failed', [
                'error' => $e->getMessage(),
                'user_id' => $id,
            ]);

            return response()->json([
                'message' => 'Failed to permanently delete user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $this->authorize('users.delete');

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userIds = $request->input('ids');
            $currentUser = $request->user();
            $deletedCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                $user = User::onlyTrashed()->find($userId);

                if (! $user) {
                    continue;
                }

                // Prevent admin from permanently deleting master users
                if ($user->hasRole('master') && ! $currentUser->hasRole('master')) {
                    $errors[] = "Cannot permanently delete master user: {$user->name}";

                    continue;
                }

                $user->forceDelete();
                $deletedCount++;
            }

            $message = $deletedCount > 0
                ? "Successfully permanently deleted {$deletedCount} user(s)"
                : 'No users were permanently deleted';

            return response()->json([
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors,
            ], $deletedCount > 0 ? 200 : 400);
        } catch (\Exception $e) {
            logger()->error('Bulk user permanent deletion failed', [
                'error' => $e->getMessage(),
                'user_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to permanently delete users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verify(User $user): JsonResponse
    {
        $this->authorize('users.update');

        try {
            // If already verified, return info
            if ($user->email_verified_at) {
                return response()->json([
                    'message' => 'User is already verified',
                    'data' => new UserResource($user),
                ]);
            }

            $user->update([
                'email_verified_at' => now(),
            ]);

            $user->load(['roles', 'media', 'links']);

            return response()->json([
                'message' => 'User verified successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            logger()->error('User verification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to verify user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function unverify(User $user): JsonResponse
    {
        $this->authorize('users.update');

        try {
            // If not verified, return info
            if (! $user->email_verified_at) {
                return response()->json([
                    'message' => 'User is already unverified',
                    'data' => new UserResource($user),
                ]);
            }

            $user->update([
                'email_verified_at' => null,
            ]);

            $user->load(['roles', 'media', 'links']);

            return response()->json([
                'message' => 'User unverified successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            logger()->error('User unverification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to unverify user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkVerify(Request $request): JsonResponse
    {
        $this->authorize('users.update');

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userIds = $request->input('ids');
            $verifiedCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if (! $user) {
                    continue;
                }

                // Skip if already verified
                if ($user->email_verified_at) {
                    continue;
                }

                $user->update([
                    'email_verified_at' => now(),
                ]);
                $verifiedCount++;
            }

            $message = $verifiedCount > 0
                ? "Successfully verified {$verifiedCount} user(s)"
                : 'No users were verified (all already verified)';

            return response()->json([
                'message' => $message,
                'verified_count' => $verifiedCount,
                'errors' => $errors,
            ], $verifiedCount > 0 ? 200 : 200);
        } catch (\Exception $e) {
            logger()->error('Bulk user verification failed', [
                'error' => $e->getMessage(),
                'user_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to verify users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkUnverify(Request $request): JsonResponse
    {
        $this->authorize('users.update');

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userIds = $request->input('ids');
            $unverifiedCount = 0;
            $errors = [];

            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if (! $user) {
                    continue;
                }

                // Skip if already unverified
                if (! $user->email_verified_at) {
                    continue;
                }

                $user->update([
                    'email_verified_at' => null,
                ]);
                $unverifiedCount++;
            }

            $message = $unverifiedCount > 0
                ? "Successfully unverified {$unverifiedCount} user(s)"
                : 'No users were unverified (all already unverified)';

            return response()->json([
                'message' => $message,
                'unverified_count' => $unverifiedCount,
                'errors' => $errors,
            ], $unverifiedCount > 0 ? 200 : 200);
        } catch (\Exception $e) {
            logger()->error('Bulk user unverification failed', [
                'error' => $e->getMessage(),
                'user_ids' => $request->input('ids'),
            ]);

            return response()->json([
                'message' => 'Failed to unverify users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
