<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProjectsExport;
use App\Exports\ProjectsTemplateExport;
use App\Helpers\LinkNormalizer;
use App\Helpers\LinkSyncHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\UserMinimalResource;
use App\Imports\ProjectsImport;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectMemberAddedNotification;
use App\Notifications\ProjectMemberRemovedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\ResponseCache\Facades\ResponseCache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query()->with(['members.media', 'media']);
        $clientOnly = $request->boolean('client_only', false);

        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        } else {
            // For client-only mode, still apply sorting from request
            $this->applySorting($query, $request);
        }

        if ($clientOnly) {
            $projects = $query->get();

            return response()->json([
                'data' => ProjectResource::collection($projects),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $projects->count(),
                    'total' => $projects->count(),
                ],
            ]);
        }

        $projects = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ProjectResource::collection($projects->items()),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::onlyTrashed()->with(['members.media', 'media']);
        $clientOnly = $request->boolean('client_only', false);

        if (! $clientOnly) {
            $this->applyFilters($query, $request);
            $this->applySorting($query, $request);
        }

        if ($clientOnly) {
            $projects = $query->get();

            return response()->json([
                'data' => ProjectResource::collection($projects),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $projects->count(),
                    'total' => $projects->count(),
                ],
            ]);
        }

        $projects = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ProjectResource::collection($projects->items()),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        if ($request->has('links') && is_array($request->links)) {
            $request->merge([
                'links' => LinkNormalizer::normalizeAll($request->links),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._]+$/',
                'not_in:'.implode(',', config('reserved_slugs')),
                'unique:projects,username',
            ],
            'bio' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'more_details' => ['nullable', 'array'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'visibility' => ['required', Rule::in(['public', 'private', 'members_only'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phones' => ['nullable', 'array'],
            'phones.*.label' => ['required', 'string', 'max:100'],
            'phones.*.number' => ['required', 'string', 'max:20'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'url', 'max:500'],
            'tmp_profile_image' => ['nullable', 'string'],
            'tmp_cover_image' => ['nullable', 'string'],
        ]);

        // Map phones to phone column
        if (isset($validated['phones'])) {
            $validated['phone'] = $validated['phones'];
            unset($validated['phones']);
        }

        $project = Project::create($validated);

        if (! empty($validated['member_ids'])) {
            $project->members()->attach($validated['member_ids']);

            // Pivot attach runs after Project::create() fired its trait clear,
            // and pivot writes do not fire the Project saved event. The public
            // project payload embeds the member list, so bust again here.
            ResponseCache::clear(['projects']);
        }

        // Handle links (skip Email/WhatsApp from form)
        if (! empty($validated['links'])) {
            foreach ($validated['links'] as $index => $link) {
                // Skip if trying to create Email or WhatsApp link manually
                if (LinkSyncHelper::isContactLink($link['label'])) {
                    continue;
                }

                $project->links()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'order' => $index,
                    'is_active' => true,
                ]);
            }
        }

        // Auto-sync Email and WhatsApp links
        LinkSyncHelper::syncProjectContactLinks($project);

        // Handle profile image upload from temporary storage
        $this->handleTemporaryUpload($request, $project, 'tmp_profile_image', 'profile_image');

        // Handle cover image upload from temporary storage
        $this->handleTemporaryUpload($request, $project, 'tmp_cover_image', 'cover_image');

        // Process content images in bio
        $this->processContentImages($project);

        return response()->json([
            'message' => 'Project created successfully',
            'data' => new ProjectResource($project->load(['members.media', 'links', 'creator', 'updater'])),
        ], 201);
    }

    public function show(string $username): JsonResponse
    {
        $project = Project::where('username', $username)
            ->with(['members.media', 'links', 'creator', 'updater'])
            ->firstOrFail();

        $this->authorize('view', $project);

        return response()->json([
            'data' => new ProjectResource($project),
        ]);
    }

    public function update(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $this->authorize('update', $project);

        if ($request->has('links') && is_array($request->links)) {
            $request->merge([
                'links' => LinkNormalizer::normalizeAll($request->links),
            ]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._]+$/',
                'not_in:'.implode(',', config('reserved_slugs')),
                Rule::unique('projects', 'username')->ignore($project->id),
            ],
            'bio' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'more_details' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
            'visibility' => ['sometimes', Rule::in(['public', 'private', 'members_only'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phones' => ['nullable', 'array'],
            'phones.*.label' => ['required', 'string', 'max:100'],
            'phones.*.number' => ['required', 'string', 'max:20'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'url', 'max:500'],
            'tmp_profile_image' => ['nullable', 'string'],
            'tmp_cover_image' => ['nullable', 'string'],
            'delete_profile_image' => ['nullable', 'boolean'],
            'delete_cover_image' => ['nullable', 'boolean'],
        ]);

        // Map phones to phone column
        if (isset($validated['phones'])) {
            $validated['phone'] = $validated['phones'];
            unset($validated['phones']);
        }

        $oldBio = $project->bio;

        // Merge the incoming settings into the existing JSON column instead of
        // overwriting it wholesale. The admin form only ever submits a single
        // top-level block (e.g. contact_form), so a plain assignment would drop
        // the other blocks (website_settings, email_subjects, hotels). A shallow
        // top-level merge replaces only the submitted keys and preserves the
        // rest. It is intentionally NOT recursive: list values like
        // email_config.to/cc/bcc must be replaced wholesale, not index-merged.
        if (array_key_exists('settings', $validated) && is_array($validated['settings'])) {
            $validated['settings'] = array_merge($project->settings ?? [], $validated['settings']);
        }

        $project->update($validated);

        // website_settings (and its rundown/events display config) live in the
        // settings JSON column. The Project trait only clears 'projects', so a
        // save touching settings must also bust the dependent public-cache tags,
        // mirroring updateWebsiteSettings().
        if (array_key_exists('settings', $validated)) {
            ResponseCache::clear(['rundown', 'events', 'website-settings']);
        }

        if (isset($validated['member_ids'])) {
            $project->members()->sync($validated['member_ids']);

            // Pivot sync does not fire the Project saved event; bust the cached
            // public project payload (which embeds the member list) manually.
            ResponseCache::clear(['projects']);
        }

        // Handle links
        if (isset($validated['links'])) {
            // Delete all existing links EXCEPT Email and WhatsApp
            $project->links()->where(function ($query) {
                $query->where('label', '!=', 'Email')
                    ->where('label', '!=', 'WhatsApp')
                    ->where('label', 'NOT LIKE', 'WhatsApp %');
            })->delete();

            // Create new links (skip Email/WhatsApp from form)
            foreach ($validated['links'] as $index => $link) {
                // Skip if trying to create Email or WhatsApp link manually
                if (LinkSyncHelper::isContactLink($link['label'])) {
                    continue;
                }

                $project->links()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'order' => $index,
                    'is_active' => true,
                ]);
            }
        }

        // Auto-sync Email and WhatsApp links
        LinkSyncHelper::syncProjectContactLinks($project);

        // Handle profile image upload from temporary storage
        $this->handleTemporaryUpload($request, $project, 'tmp_profile_image', 'profile_image');

        // Handle cover image upload from temporary storage
        $this->handleTemporaryUpload($request, $project, 'tmp_cover_image', 'cover_image');

        // Process content images in bio
        $this->processContentImages($project);
        $this->cleanupRemovedContentImages($project, $oldBio);

        return response()->json([
            'message' => 'Project updated successfully',
            'data' => new ProjectResource($project->load(['members.media', 'links', 'creator', 'updater'])),
        ]);
    }

    public function updateWebsiteSettings(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $this->authorize('update', $project);

        $validated = $request->validate([
            'rundown' => ['sometimes', 'array'],
            'rundown.show_search_bar' => ['sometimes', 'boolean'],
            'rundown.show_location_filter' => ['sometimes', 'boolean'],
            'rundown.show_all_rundown_details' => ['sometimes', 'boolean'],
            'rundown.show_rundown_on_home_page' => ['sometimes', 'boolean'],
            'brands' => ['sometimes', 'array'],
            'brands.show_brand_preview_on_home_page' => ['sometimes', 'boolean'],
            'hotels' => ['sometimes', 'array'],
            'hotels.show_hotel_section_on_home_page' => ['sometimes', 'boolean'],
            'hotels.notification_email' => ['sometimes', 'array'],
            'hotels.notification_email.to' => ['sometimes', 'array'],
            'hotels.notification_email.to.*' => ['nullable', 'email'],
            'hotels.notification_email.cc' => ['sometimes', 'array'],
            'hotels.notification_email.cc.*' => ['nullable', 'email'],
            'hotels.notification_email.bcc' => ['sometimes', 'array'],
            'hotels.notification_email.bcc.*' => ['nullable', 'email'],
            // Per-email-type subject templates. Null/empty = use default.
            // Max 120 chars matches the average Gmail/Outlook subject preview
            // budget after the recipient prefix collapses long inbox lines.
            'email_subjects' => ['sometimes', 'array'],
            'email_subjects.guest_paid' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email_subjects.guest_voucher' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email_subjects.guest_cancelled' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email_subjects.staff_confirmed' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email_subjects.staff_cancelled' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $settings = $project->settings ?? [];
        $current = data_get($settings, 'website_settings', []);
        $merged = array_replace_recursive($current, $validated);

        // array_replace_recursive merges list arrays by index, which would
        // resurrect recipients the user just removed. Replace the hotel
        // notification email block wholesale whenever it is part of the payload.
        if (array_key_exists('notification_email', $validated['hotels'] ?? [])) {
            data_set($merged, 'hotels.notification_email', $validated['hotels']['notification_email']);
        }

        data_set($settings, 'website_settings', $merged);

        $project->settings = $settings;
        $project->save();

        // Public rundown / events / website-settings responses cache
        // `website_settings` from the owning project. The Project model only
        // clears the 'projects' tag on save, so explicitly invalidate the
        // dependent caches here.
        ResponseCache::clear(['rundown', 'events', 'website-settings']);

        return response()->json([
            'message' => 'Website settings updated successfully',
            'data' => [
                'website_settings' => data_get($project->settings, 'website_settings', []),
            ],
        ]);
    }

    public function destroy(string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $this->authorize('delete', $project);

        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $projects = Project::whereIn('id', $validated['ids'])->get();

        $deletedCount = 0;
        $errors = [];

        foreach ($projects as $project) {
            try {
                if (auth()->user()->can('delete', $project)) {
                    $project->delete();
                    $deletedCount++;
                } else {
                    $errors[] = [
                        'id' => $project->id,
                        'name' => $project->name,
                        'error' => 'Unauthorized',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$deletedCount} project(s) deleted successfully",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $project = Project::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $project);

        $project->restore();

        return response()->json([
            'message' => 'Project restored successfully',
            'data' => new ProjectResource($project->load(['members.media', 'links', 'creator', 'updater'])),
        ]);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $projects = Project::onlyTrashed()->whereIn('id', $validated['ids'])->get();

        $restoredCount = 0;
        $errors = [];

        foreach ($projects as $project) {
            try {
                if (auth()->user()->can('restore', $project)) {
                    $project->restore();
                    $restoredCount++;
                } else {
                    $errors[] = [
                        'id' => $project->id,
                        'name' => $project->name,
                        'error' => 'Unauthorized',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$restoredCount} project(s) restored successfully",
            'restored_count' => $restoredCount,
            'errors' => $errors,
        ]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $project = Project::onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $project);

        $project->forceDelete();

        return response()->json([
            'message' => 'Project permanently deleted',
        ]);
    }

    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $projects = Project::onlyTrashed()->whereIn('id', $validated['ids'])->get();

        $deletedCount = 0;
        $errors = [];

        foreach ($projects as $project) {
            try {
                if (auth()->user()->can('forceDelete', $project)) {
                    $project->forceDelete();
                    $deletedCount++;
                } else {
                    $errors[] = [
                        'id' => $project->id,
                        'name' => $project->name,
                        'error' => 'Unauthorized',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$deletedCount} project(s) permanently deleted",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function updateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:projects,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        // Authorize - user must be able to update project ordering
        $this->authorize('updateOrder', Project::class);

        // Build CASE statement for batch update
        $cases = [];
        $ids = [];
        $params = [];

        foreach ($validated['orders'] as $index => $orderData) {
            $cases[] = 'WHEN id = ? THEN ?::integer';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        // Execute batch update in single query with explicit integer casting for PostgreSQL
        \DB::statement(
            "UPDATE projects SET order_column = CASE {$casesString} END WHERE id IN ({$idsString})",
            $params
        );

        return response()->json([
            'message' => 'Project order updated successfully',
        ]);
    }

    public function toggleMember(Request $request, string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();

        $this->authorize('update', $project);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $userId = $validated['user_id'];
        $user = User::findOrFail($userId);

        if ($project->members()->where('user_id', $userId)->exists()) {
            $project->members()->detach($userId);
            $action = 'removed';
        } else {
            $project->members()->attach($userId);
            $action = 'added';
        }

        // Pivot attach/detach does not fire the Project saved event; the public
        // project payload embeds the member list, so bust the cache manually.
        ResponseCache::clear(['projects']);

        activity()
            ->performedOn($project)
            ->causedBy($request->user())
            ->withProperties([
                'project_id' => $project->id,
                'member_name' => $user->name,
                'member_id' => $userId,
                'action' => $action,
            ])
            ->event($action === 'added' ? 'member_added' : 'member_removed')
            ->log($action === 'added' ? "Added member {$user->name}" : "Removed member {$user->name}");

        // Notify the member (if not self)
        if ($userId !== $request->user()->id) {
            if ($action === 'added') {
                $user->notify(new ProjectMemberAddedNotification($project, $request->user()));
            } else {
                $user->notify(new ProjectMemberRemovedNotification($project, $request->user()));
            }
        }

        return response()->json([
            'message' => $action === 'added'
                ? "{$user->name} added to {$project->name}"
                : "{$user->name} removed from {$project->name}",
            'action' => $action,
            'user_id' => $userId,
        ]);
    }

    public function getEligibleMembers(): JsonResponse
    {
        $users = User::role(['master', 'admin', 'staff'])
            ->with('media')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => UserMinimalResource::collection($users),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Project::class);

        // Get filters and sorting from request
        // Note: Laravel converts dots in query params to underscores
        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }
        if ($status = $request->input('filter_status')) {
            $filters['status'] = $status;
        }
        if ($visibility = $request->input('filter_visibility')) {
            $filters['visibility'] = $visibility;
        }

        $sort = $request->input('sort', 'order_column');

        // Create the export with filters and sorting
        $export = new ProjectsExport($filters, $sort);

        // Generate filename with timestamp
        $filename = 'projects_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'model_type' => 'Project',
                'filename' => $filename,
            ])
            ->log('Exported projects');

        return Excel::download($export, $filename);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('create', Project::class);

        $filename = 'projects_import_template.xlsx';

        return Excel::download(new ProjectsTemplateExport, $filename);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

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

            if (! Storage::disk('local')->exists($metadataPath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            $metadata = json_decode(
                Storage::disk('local')->get($metadataPath),
                true
            );

            $filePath = "tmp/uploads/{$tempFolder}/{$metadata['original_name']}";

            if (! Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            // Import projects
            $import = new ProjectsImport;
            Excel::import($import, Storage::disk('local')->path($filePath));

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
                'message' => 'Projects imported successfully',
                'imported_count' => $importedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('Project import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import projects',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            // Always clean up temporary files
            if ($tempFolder) {
                Storage::disk('local')->deleteDirectory("tmp/uploads/{$tempFolder}");
            }
        }
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->has('filter.search')) {
            $search = $request->input('filter.search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('filter.status')) {
            $statuses = explode(',', $request->input('filter.status'));
            $query->whereIn('status', $statuses);
        }

        if ($request->has('filter.visibility')) {
            $visibilities = explode(',', $request->input('filter.visibility'));
            $query->whereIn('visibility', $visibilities);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sort = $request->input('sort', 'order_column');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        $query->orderBy($field, $direction);
    }

    /**
     * Handle temporary file upload and move to media collection.
     */
    private function handleTemporaryUpload(Request $request, Project $project, string $fieldName, string $collection): void
    {
        // Check for delete flag first
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $project->clearMediaCollection($collection);

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
        if (! Str::startsWith($value, 'tmp-')) {
            return;
        }

        // Handle new upload from temporary storage
        $metadataPath = "tmp/uploads/{$value}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(
            Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        // Clear existing media in this collection first
        $project->clearMediaCollection($collection);

        // Add new media
        $project->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        // Clean up temporary files
        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }

    private function processContentImages(Project $project): void
    {
        if (! $project->bio) {
            return;
        }

        $content = $project->bio;
        $pattern = '/<img[^>]+src="(?:https?:\/\/[^\/]+)?\/api\/tmp-media\/(tmp-media-[a-zA-Z0-9._-]+)"[^>]*>/';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $fullImgTag = $match[0];
            $folder = $match[1];

            try {
                $metadataPath = "tmp/uploads/{$folder}/metadata.json";

                if (! Storage::disk('local')->exists($metadataPath)) {
                    continue;
                }

                $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
                $filename = $metadata['original_name'];
                $tempFilePath = "tmp/uploads/{$folder}/{$filename}";

                if (! Storage::disk('local')->exists($tempFilePath)) {
                    continue;
                }

                $caption = null;
                if (preg_match('/data-caption="([^"]*)"/', $fullImgTag, $captionMatch)) {
                    $caption = html_entity_decode($captionMatch[1]);
                }

                $baseName = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $uniqueBase = $baseName.'-'.substr($folder, -8);
                $uniqueFileName = $uniqueBase.($extension ? '.'.$extension : '');

                $mediaAdder = $project->addMediaFromDisk($tempFilePath, 'local')
                    ->usingName($uniqueBase)
                    ->usingFileName($uniqueFileName);

                if ($caption) {
                    $mediaAdder->withCustomProperties(['caption' => $caption]);
                }

                $media = $mediaAdder->toMediaCollection('bio_images');

                $responsiveImg = $this->buildResponsiveImageHtml($media, $caption);
                $content = str_replace($fullImgTag, $responsiveImg, $content);

                Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");
            } catch (\Exception $e) {
                logger()->warning('Failed to process content image', [
                    'folder' => $folder,
                    'error' => $e->getMessage(),
                    'project_id' => $project->id,
                ]);
            }
        }

        if ($content !== $project->bio) {
            $project->update(['bio' => $content]);
        }
    }

    private function buildResponsiveImageHtml($media, ?string $caption = null): string
    {
        $alt = $caption ?? $media->getCustomProperty('caption') ?? $media->name;

        $srcset = [
            $media->getUrl('sm').' 600w',
            $media->getUrl('md').' 900w',
            $media->getUrl('lg').' 1200w',
            $media->getUrl('xl').' 1600w',
        ];

        $srcsetString = implode(', ', $srcset);
        $sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 1200px';

        $captionAttr = $caption
            ? sprintf(' data-caption="%s"', htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'))
            : '';

        return sprintf(
            '<img src="%s" srcset="%s" sizes="%s" alt="%s"%s loading="lazy" class="w-full h-auto rounded-lg">',
            $media->getUrl('lg'),
            $srcsetString,
            $sizes,
            htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'),
            $captionAttr
        );
    }

    private function cleanupRemovedContentImages(Project $project, ?string $oldBio): void
    {
        $contentImages = $project->getMedia('bio_images');

        if ($contentImages->isEmpty()) {
            return;
        }

        $currentContent = $project->bio ?? '';

        foreach ($contentImages as $media) {
            if (! $this->isMediaUsedInContent($media, $currentContent)) {
                try {
                    $media->delete();
                } catch (\Exception $e) {
                    logger()->warning('Failed to cleanup removed bio image', [
                        'project_id' => $project->id,
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function isMediaUsedInContent($media, string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        $filename = $media->file_name;

        if (str_contains($content, $filename)) {
            return true;
        }

        $encodedFilename = rawurlencode($filename);
        if (str_contains($content, $encodedFilename)) {
            return true;
        }

        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        if (str_contains($content, $baseName)) {
            return true;
        }

        return false;
    }
}
