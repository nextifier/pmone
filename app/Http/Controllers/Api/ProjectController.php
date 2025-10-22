<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\UserMinimalResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query()->with(['members']);
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

        $query = Project::onlyTrashed()->with(['members']);
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:30',
                'min:1',
                'regex:/^[a-z0-9\-]+$/',
                'not_in:'.implode(',', config('reserved_slugs')),
                'unique:projects,username',
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'more_details' => ['nullable', 'array'],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'visibility' => ['required', Rule::in(['public', 'private', 'members_only'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'array'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'url', 'max:500'],
        ]);

        $project = Project::create($validated);

        if (! empty($validated['member_ids'])) {
            $project->members()->attach($validated['member_ids']);
        }

        // Handle links
        if (! empty($validated['links'])) {
            foreach ($validated['links'] as $index => $link) {
                $project->links()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'order' => $index,
                    'is_active' => true,
                ]);
            }
        }

        return response()->json([
            'message' => 'Project created successfully',
            'data' => new ProjectResource($project->load(['members', 'links', 'creator', 'updater'])),
        ], 201);
    }

    public function show(string $username): JsonResponse
    {
        $project = Project::where('username', $username)
            ->with(['members', 'links', 'creator', 'updater'])
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

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'max:30',
                'min:1',
                'regex:/^[a-z0-9\-]+$/',
                'not_in:'.implode(',', config('reserved_slugs')),
                Rule::unique('projects', 'username')->ignore($project->id),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'more_details' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'archived'])],
            'visibility' => ['sometimes', Rule::in(['public', 'private', 'members_only'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'array'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'url', 'max:500'],
        ]);

        $project->update($validated);

        if (isset($validated['member_ids'])) {
            $project->members()->sync($validated['member_ids']);
        }

        // Handle links
        if (isset($validated['links'])) {
            // Delete all existing links
            $project->links()->delete();

            // Create new links
            foreach ($validated['links'] as $index => $link) {
                $project->links()->create([
                    'label' => $link['label'],
                    'url' => $link['url'],
                    'order' => $index,
                    'is_active' => true,
                ]);
            }
        }

        return response()->json([
            'message' => 'Project updated successfully',
            'data' => new ProjectResource($project->load(['members', 'links', 'creator', 'updater'])),
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
            'data' => new ProjectResource($project->load(['members', 'links', 'creator', 'updater'])),
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

    public function getEligibleMembers(): JsonResponse
    {
        $users = User::role(['master', 'admin', 'staff'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => UserMinimalResource::collection($users),
        ]);
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
}
