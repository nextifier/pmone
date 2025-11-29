<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiConsumerRequest;
use App\Http\Requests\UpdateApiConsumerRequest;
use App\Http\Resources\ApiConsumerResource;
use App\Models\ApiConsumer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiConsumerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of API consumers
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiConsumer::class);

        $query = ApiConsumer::query()
            ->with('creator')
            ->latest();

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('website_url', 'like', "%{$search}%");
            });
        }

        $consumers = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ApiConsumerResource::collection($consumers->items()),
            'meta' => [
                'current_page' => $consumers->currentPage(),
                'last_page' => $consumers->lastPage(),
                'per_page' => $consumers->perPage(),
                'total' => $consumers->total(),
            ],
        ]);
    }

    /**
     * Store a newly created API consumer
     */
    public function store(StoreApiConsumerRequest $request): JsonResponse
    {
        $this->authorize('create', ApiConsumer::class);

        $consumer = ApiConsumer::create([
            'name' => $request->name,
            'website_url' => $request->website_url,
            'description' => $request->description,
            'allowed_origins' => $request->allowed_origins,
            'rate_limit' => $request->has('rate_limit') ? $request->rate_limit : 60,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'API Consumer created successfully',
            'data' => new ApiConsumerResource($consumer),
        ], 201);
    }

    /**
     * Display the specified API consumer
     */
    public function show(ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('view', $apiConsumer);

        $apiConsumer->load('creator');

        return response()->json([
            'data' => new ApiConsumerResource($apiConsumer),
        ]);
    }

    /**
     * Update the specified API consumer
     */
    public function update(UpdateApiConsumerRequest $request, ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('update', $apiConsumer);

        $apiConsumer->update([
            'name' => $request->name,
            'website_url' => $request->website_url,
            'description' => $request->description,
            'allowed_origins' => $request->allowed_origins,
            'rate_limit' => $request->rate_limit,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'API Consumer updated successfully',
            'data' => new ApiConsumerResource($apiConsumer->fresh()),
        ]);
    }

    /**
     * Remove the specified API consumer
     */
    public function destroy(ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('delete', $apiConsumer);

        $apiConsumer->update(['deleted_by' => auth()->id()]);
        $apiConsumer->delete();

        return response()->json([
            'message' => 'API Consumer deleted successfully',
        ]);
    }

    /**
     * Regenerate API key for the specified consumer
     */
    public function regenerateKey(ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('update', $apiConsumer);

        $newApiKey = ApiConsumer::generateApiKey();
        $apiConsumer->update([
            'api_key' => $newApiKey,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'API key regenerated successfully',
            'data' => [
                'api_key' => $newApiKey,
            ],
        ]);
    }

    /**
     * Toggle active status for the specified consumer
     */
    public function toggleStatus(ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('update', $apiConsumer);

        $apiConsumer->update([
            'is_active' => ! $apiConsumer->is_active,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'API Consumer status updated successfully',
            'data' => new ApiConsumerResource($apiConsumer->fresh()),
        ]);
    }

    /**
     * Get usage statistics for the specified consumer
     */
    public function statistics(ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('view', $apiConsumer);

        return response()->json([
            'data' => [
                'consumer' => new ApiConsumerResource($apiConsumer),
                'statistics' => [
                    'last_used_at' => $apiConsumer->last_used_at,
                    'days_since_last_use' => $apiConsumer->last_used_at
                        ? now()->diffInDays($apiConsumer->last_used_at)
                        : null,
                    'is_active' => $apiConsumer->is_active,
                    'rate_limit' => $apiConsumer->rate_limit,
                    'allowed_origins_count' => count($apiConsumer->allowed_origins ?? []),
                ],
            ],
        ]);
    }

    /**
     * Display a listing of trashed API consumers
     */
    public function trash(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiConsumer::class);

        $query = ApiConsumer::onlyTrashed()->with(['creator', 'deleter']);
        $clientOnly = $request->boolean('client_only', false);

        if (! $clientOnly) {
            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('website_url', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sort = $request->input('sort', '-deleted_at');
            $sortField = ltrim($sort, '-');
            $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $query->orderBy($sortField, $sortDirection);
        }

        if ($clientOnly) {
            $consumers = $query->get();

            return response()->json([
                'data' => ApiConsumerResource::collection($consumers),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $consumers->count(),
                    'total' => $consumers->count(),
                ],
            ]);
        }

        $consumers = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => ApiConsumerResource::collection($consumers->items()),
            'meta' => [
                'current_page' => $consumers->currentPage(),
                'last_page' => $consumers->lastPage(),
                'per_page' => $consumers->perPage(),
                'total' => $consumers->total(),
            ],
        ]);
    }

    /**
     * Restore a trashed API consumer
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $consumer = ApiConsumer::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $consumer);

        $consumer->restore();

        return response()->json([
            'message' => 'API Consumer restored successfully',
        ]);
    }

    /**
     * Restore multiple trashed API consumers
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $this->authorize('restoreAny', ApiConsumer::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:api_consumers,id',
        ]);

        $consumers = ApiConsumer::onlyTrashed()->whereIn('id', $request->ids)->get();

        foreach ($consumers as $consumer) {
            $this->authorize('restore', $consumer);
        }

        ApiConsumer::onlyTrashed()->whereIn('id', $request->ids)->restore();

        return response()->json([
            'message' => count($request->ids).' API Consumer(s) restored successfully',
            'restored_count' => count($request->ids),
        ]);
    }

    /**
     * Permanently delete a trashed API consumer
     */
    public function forceDestroy(Request $request, int $id): JsonResponse
    {
        $consumer = ApiConsumer::onlyTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $consumer);

        $consumer->forceDelete();

        return response()->json([
            'message' => 'API Consumer permanently deleted',
        ]);
    }

    /**
     * Permanently delete multiple trashed API consumers
     */
    public function bulkForceDestroy(Request $request): JsonResponse
    {
        $this->authorize('forceDeleteAny', ApiConsumer::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:api_consumers,id',
        ]);

        $consumers = ApiConsumer::onlyTrashed()->whereIn('id', $request->ids)->get();

        foreach ($consumers as $consumer) {
            $this->authorize('forceDelete', $consumer);
        }

        ApiConsumer::onlyTrashed()->whereIn('id', $request->ids)->forceDelete();

        return response()->json([
            'message' => count($request->ids).' API Consumer(s) permanently deleted',
            'deleted_count' => count($request->ids),
        ]);
    }
}
