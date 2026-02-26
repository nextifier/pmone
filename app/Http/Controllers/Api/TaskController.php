<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskStatusChangedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();

        // /tasks - Show only tasks assigned to current user
        $query = Task::query()
            ->where('assignee_id', $user->id)
            ->with(['assignee.media', 'project.media', 'creator']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $tasks = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();

        $query = Task::query()->with(['assignee.media', 'project.media', 'creator']);

        // Master sees everything, others see filtered by visibility
        if (! $user->hasRole('master')) {
            $query->visibleTo($user);
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $tasks = $query->get();

        // Group by assignee_id
        $grouped = $tasks->groupBy('assignee_id')->map(function ($tasks, $assigneeId) {
            $assignee = $tasks->first()->assignee;

            return [
                'assignee' => $assignee ? [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'username' => $assignee->username,
                    'profile_image' => $assignee->relationLoaded('media') && $assignee->hasMedia('profile_image')
                        ? $assignee->getMediaUrls('profile_image')
                        : null,
                ] : null,
                'tasks' => TaskResource::collection($tasks),
                'count' => $tasks->count(),
            ];
        })->values();

        return response()->json([
            'data' => $grouped,
        ]);
    }

    public function userTasks(Request $request, string $username): JsonResponse
    {
        $targetUser = User::where('username', $username)->firstOrFail();
        $viewer = $request->user();

        $query = Task::query()
            ->where('assignee_id', $targetUser->id)
            ->with(['assignee.media', 'project.media', 'creator']);

        // Master sees all tasks for this user
        if (! $viewer->hasRole('master')) {
            $query->where(function ($q) use ($viewer) {
                $q->where('visibility', Task::VISIBILITY_PUBLIC)
                    ->orWhere(function ($q2) use ($viewer) {
                        $q2->where('visibility', Task::VISIBILITY_SHARED)
                            ->whereHas('sharedUsers', fn ($s) => $s->where('user_id', $viewer->id));
                    })
                    ->orWhere('created_by', $viewer->id)
                    ->orWhere('assignee_id', $viewer->id);
            });
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $tasks = $query->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'username' => $targetUser->username,
                'title' => $targetUser->title,
                'profile_image' => $targetUser->hasMedia('profile_image')
                    ? $targetUser->getMediaUrls('profile_image')
                    : null,
            ],
        ]);
    }

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::onlyTrashed()->with(['assignee.media', 'project.media', 'creator']);

        // Non-admin users see only their accessible tasks
        $user = $request->user();
        if (! $user->hasRole(['master', 'admin'])) {
            $query->visibleTo($user);
        }

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $tasks = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $task = Task::create([
            ...$request->safe()->except(['shared_user_ids', 'shared_roles', 'description_images']),
            'created_by' => $request->user()->id,
        ]);

        // Handle shared users
        if ($request->input('visibility') === Task::VISIBILITY_SHARED && $request->has('shared_user_ids')) {
            $this->syncSharedUsers($task, $request->input('shared_user_ids'), $request->input('shared_roles', []));
        }

        // Process content images (move from temp to permanent storage)
        $this->processContentImages($task);

        // Notify assignee (if not self-assign)
        if ($task->assignee_id && $task->assignee_id !== $request->user()->id) {
            $task->assignee->notify(new TaskAssignedNotification($task, $request->user()));
        }

        return response()->json([
            'message' => 'Task created successfully',
            'data' => new TaskResource($task->load(['assignee', 'sharedUsers', 'project', 'creator'])),
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $task = Task::where('ulid', $ulid)
            ->with(['assignee.media', 'sharedUsers', 'project.media', 'creator', 'updater', 'media'])
            ->firstOrFail();

        $this->authorize('view', $task);

        return response()->json([
            'data' => new TaskResource($task),
        ]);
    }

    public function update(UpdateTaskRequest $request, string $ulid): JsonResponse
    {
        $task = Task::where('ulid', $ulid)->firstOrFail();

        $this->authorize('update', $task);

        // Store old values before update for comparison
        $oldDescription = $task->description;
        $oldAssigneeId = $task->assignee_id;
        $oldStatus = $task->status;

        $task->update([
            ...$request->safe()->except(['shared_user_ids', 'shared_roles', 'description_images']),
            'updated_by' => $request->user()->id,
        ]);

        // Sync shared users if visibility changed or users updated
        if ($request->has('shared_user_ids')) {
            if ($task->visibility === Task::VISIBILITY_SHARED) {
                $this->syncSharedUsers($task, $request->input('shared_user_ids'), $request->input('shared_roles', []));
            } else {
                // If visibility changed from shared to other, detach all
                $task->sharedUsers()->detach();
            }
        }

        // Process content images (move from temp to permanent storage)
        $this->processContentImages($task);

        // Cleanup removed content images
        $this->cleanupRemovedContentImages($task, $oldDescription);

        // Notify new assignee (if changed and not self-assign)
        if ($task->assignee_id && $task->assignee_id !== $oldAssigneeId && $task->assignee_id !== $request->user()->id) {
            $task->assignee->notify(new TaskAssignedNotification($task, $request->user()));
        }

        // Notify assignee when status changed (if not self)
        if ($request->has('status') && $task->status !== $oldStatus && $task->assignee_id && $task->assignee_id !== $request->user()->id) {
            $task->assignee->notify(new TaskStatusChangedNotification($task, $task->status, $request->user()));
        }

        return response()->json([
            'message' => 'Task updated successfully',
            'data' => new TaskResource($task->load(['assignee', 'sharedUsers', 'project', 'creator', 'updater'])),
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $task = Task::where('ulid', $ulid)->firstOrFail();

        $this->authorize('delete', $task);

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        $tasks = Task::whereIn('id', $validated['ids'])->get();

        $deletedCount = 0;
        $errors = [];

        foreach ($tasks as $task) {
            try {
                if (auth()->user()->can('delete', $task)) {
                    $task->delete();
                    $deletedCount++;
                } else {
                    $errors[] = [
                        'id' => $task->id,
                        'title' => $task->title,
                        'error' => 'Unauthorized',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$deletedCount} task(s) deleted successfully",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $task = Task::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $task);

        $task->restore();

        return response()->json([
            'message' => 'Task restored successfully',
            'data' => new TaskResource($task->load(['assignee', 'sharedUsers', 'project', 'creator'])),
        ]);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $tasks = Task::onlyTrashed()->whereIn('id', $validated['ids'])->get();

        $restoredCount = 0;
        $errors = [];

        foreach ($tasks as $task) {
            try {
                if (auth()->user()->can('restore', $task)) {
                    $task->restore();
                    $restoredCount++;
                } else {
                    $errors[] = [
                        'id' => $task->id,
                        'title' => $task->title,
                        'error' => 'Unauthorized',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$restoredCount} task(s) restored successfully",
            'restored_count' => $restoredCount,
            'errors' => $errors,
        ]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $task = Task::onlyTrashed()->findOrFail($id);

        $this->authorize('forceDelete', $task);

        $task->forceDelete();

        return response()->json([
            'message' => 'Task permanently deleted',
        ]);
    }

    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $this->authorize('forceDeleteAny', Task::class);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $tasks = Task::onlyTrashed()->whereIn('id', $validated['ids'])->get();

        $deletedCount = 0;
        $errors = [];

        foreach ($tasks as $task) {
            try {
                if (auth()->user()->can('forceDelete', $task)) {
                    $task->forceDelete();
                    $deletedCount++;
                } else {
                    $errors[] = [
                        'id' => $task->id,
                        'title' => $task->title,
                        'error' => 'Unauthorized',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$deletedCount} task(s) permanently deleted",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    public function updateOrder(Request $request): JsonResponse
    {
        $this->authorize('updateOrder', Task::class);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:tasks,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        foreach ($validated['orders'] as $orderData) {
            $cases[] = 'WHEN id = ? THEN ?::integer';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        \DB::statement(
            "UPDATE tasks SET order_column = CASE {$casesString} END WHERE id IN ({$idsString})",
            $params
        );

        return response()->json([
            'message' => 'Task order updated successfully',
        ]);
    }

    // Helper methods

    private function syncSharedUsers(Task $task, array $userIds, array $roles): void
    {
        $sharedUsers = [];

        foreach ($userIds as $userId) {
            $sharedUsers[$userId] = [
                'role' => $roles[$userId] ?? Task::SHARED_ROLE_VIEWER,
            ];
        }

        $task->sharedUsers()->sync($sharedUsers);
    }

    /**
     * Process content images - move temporary images to permanent storage
     */
    private function processContentImages(Task $task): void
    {
        if (! $task->description) {
            return;
        }

        $content = $task->description;
        // Match both relative URLs (/api/tmp-media/...) and absolute URLs (http://host/api/tmp-media/...)
        $pattern = '/<img[^>]+src="(?:https?:\/\/[^\/]+)?\/api\/tmp-media\/(tmp-media-[a-zA-Z0-9._-]+)"[^>]*>/';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $fullImgTag = $match[0];
            $folder = $match[1];

            try {
                $metadataPath = "tmp/uploads/{$folder}/metadata.json";

                if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($metadataPath)) {
                    continue;
                }

                $metadata = json_decode(\Illuminate\Support\Facades\Storage::disk('local')->get($metadataPath), true);
                $filename = $metadata['original_name'];
                $tempFilePath = "tmp/uploads/{$folder}/{$filename}";

                if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($tempFilePath)) {
                    continue;
                }

                // Extract caption from data-caption attribute if exists
                $caption = null;
                if (preg_match('/data-caption="([^"]*)"/', $fullImgTag, $captionMatch)) {
                    $caption = html_entity_decode($captionMatch[1]);
                }

                // Move file from temp storage to permanent storage
                $mediaAdder = $task->addMediaFromDisk($tempFilePath, 'local')
                    ->usingName(pathinfo($filename, PATHINFO_FILENAME));

                if ($caption) {
                    $mediaAdder->withCustomProperties(['caption' => $caption]);
                }

                $media = $mediaAdder->toMediaCollection('description_images');

                // Build responsive image HTML with srcset
                $responsiveImg = $this->buildResponsiveImageHtml($media, $caption);

                // Replace entire img tag with responsive version
                $content = str_replace($fullImgTag, $responsiveImg, $content);

                // Clean up temporary storage
                \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");
            } catch (\Exception $e) {
                logger()->warning('Failed to process content image', [
                    'folder' => $folder,
                    'error' => $e->getMessage(),
                    'task_id' => $task->id,
                ]);
            }
        }

        if ($content !== $task->description) {
            $task->update(['description' => $content]);
        }
    }

    /**
     * Build responsive image HTML with srcset for content images
     */
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

        $html = sprintf(
            '<img src="%s" srcset="%s" sizes="%s" alt="%s"%s loading="lazy" class="w-full h-auto rounded-lg">',
            $media->getUrl('lg'),
            $srcsetString,
            $sizes,
            htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'),
            $captionAttr
        );

        return $html;
    }

    /**
     * Cleanup content images that were removed from task description
     */
    private function cleanupRemovedContentImages(Task $task, ?string $oldDescription): void
    {
        $contentImages = $task->getMedia('description_images');

        if ($contentImages->isEmpty()) {
            return;
        }

        $currentContent = $task->description ?? '';

        foreach ($contentImages as $media) {
            if (! $this->isMediaUsedInContent($media, $currentContent)) {
                try {
                    $media->delete();

                    logger()->info('Deleted orphaned description image', [
                        'task_id' => $task->id,
                        'media_id' => $media->id,
                        'file_name' => $media->file_name,
                    ]);
                } catch (\Exception $e) {
                    logger()->warning('Failed to cleanup removed description image', [
                        'task_id' => $task->id,
                        'media_id' => $media->id,
                        'file_name' => $media->file_name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Check if a media file is used in content
     */
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

        $encodedBaseName = rawurlencode($baseName);
        if (str_contains($content, $encodedBaseName)) {
            return true;
        }

        return false;
    }

    private function applyFilters($query, Request $request): void
    {
        // Search
        if ($search = $request->input('filter_search')) {
            $query->search($search);
        }

        // Status filter
        if ($status = $request->input('filter_status')) {
            $query->byStatus(is_array($status) ? $status : [$status]);
        }

        // Priority filter
        if ($priority = $request->input('filter_priority')) {
            $query->byPriority(is_array($priority) ? $priority : [$priority]);
        }

        // Complexity filter
        if ($complexity = $request->input('filter_complexity')) {
            $query->byComplexity(is_array($complexity) ? $complexity : [$complexity]);
        }

        // Visibility filter
        if ($visibility = $request->input('filter_visibility')) {
            $query->where('visibility', $visibility);
        }

        // Assignee filter
        if ($assignee = $request->input('filter_assignee')) {
            $query->where('assignee_id', $assignee);
        }

        // Project filter
        if ($project = $request->input('filter_project')) {
            $query->where('project_id', $project);
        }

        // Overdue filter
        if ($request->boolean('filter_overdue')) {
            $query->overdue();
        }

        // Upcoming filter
        if ($request->has('filter_upcoming_days')) {
            $query->upcoming((int) $request->input('filter_upcoming_days', 7));
        }

        // Creator filter
        if ($creator = $request->input('filter_creator')) {
            $query->where('created_by', $creator);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortFields = [
            'title',
            'status',
            'priority',
            'complexity',
            'estimated_start_at',
            'estimated_completion_at',
            'completed_at',
            'created_at',
            'updated_at',
            'order_column',
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }
    }
}
