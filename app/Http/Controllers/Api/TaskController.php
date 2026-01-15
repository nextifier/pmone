<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::query()->with(['assignee', 'project', 'creator']);

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

    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::onlyTrashed()->with(['assignee', 'project', 'creator']);

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

        // Handle TipTap description images (convert temporary uploads to permanent)
        if ($request->has('description_images')) {
            $this->attachDescriptionImages($task, $request->input('description_images'));
        }

        return response()->json([
            'message' => 'Task created successfully',
            'data' => new TaskResource($task->load(['assignee', 'sharedUsers', 'project', 'creator'])),
        ], 201);
    }

    public function show(string $ulid): JsonResponse
    {
        $task = Task::where('ulid', $ulid)
            ->with(['assignee', 'sharedUsers', 'project', 'creator', 'updater', 'media'])
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

        // Handle description images
        if ($request->has('description_images')) {
            $this->attachDescriptionImages($task, $request->input('description_images'));
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

    private function attachDescriptionImages(Task $task, array $temporaryUploadUuids): void
    {
        foreach ($temporaryUploadUuids as $uuid) {
            // Find temporary media and move to task's collection
            $tempMedia = Media::where('uuid', $uuid)->first();

            if ($tempMedia) {
                $tempMedia->move($task, 'description_images');
            }
        }
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
