<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiConsumerRequest;
use App\Http\Requests\UpdateApiConsumerRequest;
use App\Http\Resources\ApiConsumerResource;
use App\Models\ApiConsumer;
use App\Services\ApiConsumerAnalyticsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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

        if ($request->has('project_ids')) {
            $consumer->projects()->sync($request->input('project_ids', []));
        }

        $consumer->load('creator', 'projects');

        return response()->json([
            'message' => 'API Consumer created successfully',
            'data' => new ApiConsumerResource($consumer),
            // Raw key shown exactly once, right here, right after
            // generation. No other endpoint (show, index, ...) ever returns
            // it, and ApiConsumerResource never includes it.
            'key' => $consumer->api_key,
        ], 201);
    }

    /**
     * Display the specified API consumer
     */
    public function show(ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('view', $apiConsumer);

        $apiConsumer->load('creator', 'projects');

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

        if ($request->has('project_ids')) {
            $apiConsumer->projects()->sync($request->input('project_ids', []));
        }

        return response()->json([
            'message' => 'API Consumer updated successfully',
            'data' => new ApiConsumerResource($apiConsumer->fresh(['creator', 'projects'])),
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

        $apiConsumer->updated_by = auth()->id();
        $newApiKey = $apiConsumer->regenerateApiKey();

        activity()
            ->causedBy(auth()->user())
            ->performedOn($apiConsumer)
            ->event('api_key_regenerated')
            ->withProperties(['consumer_name' => $apiConsumer->name])
            ->log("Regenerated API key for {$apiConsumer->name}");

        return response()->json([
            'message' => 'API key regenerated successfully',
            // Raw key shown exactly once, right here. Never returned again.
            'key' => $newApiKey,
        ]);
    }

    /**
     * Reveal the current raw API key on demand.
     *
     * The plaintext is still stored in the `api_key` column, so it can be
     * shown again instead of only once at create/regenerate. Gated behind the
     * `update` ability (parity with regenerate) and audited so every reveal
     * leaves a trail of who accessed the key.
     */
    public function revealKey(ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('update', $apiConsumer);

        if (empty($apiConsumer->api_key)) {
            return response()->json([
                'message' => 'Raw key unavailable — please regenerate.',
            ], 422);
        }

        activity()
            ->causedBy(auth()->user())
            ->performedOn($apiConsumer)
            ->event('api_key_revealed')
            ->withProperties(['consumer_name' => $apiConsumer->name])
            ->log("Revealed API key for {$apiConsumer->name}");

        return response()->json([
            'key' => $apiConsumer->api_key,
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
     * Get analytics data for the specified consumer
     */
    public function analytics(Request $request, ApiConsumer $apiConsumer, ApiConsumerAnalyticsService $analyticsService): JsonResponse
    {
        $this->authorize('view', $apiConsumer);

        [$startDate, $endDate, $days] = $this->resolveAnalyticsRange($request, $analyticsService);

        return response()->json([
            'data' => [
                'consumer' => new ApiConsumerResource($apiConsumer),
                'period' => $analyticsService->buildPeriodData($days, $startDate, $endDate),
                'summary' => $analyticsService->getSummary($apiConsumer->id, $startDate, endDate: $endDate),
                'requests_per_day' => $analyticsService->getRequestsPerDay($apiConsumer->id, $startDate, $endDate),
                'top_endpoints' => $analyticsService->getTopEndpoints($apiConsumer->id, $startDate, endDate: $endDate),
                'status_distribution' => $analyticsService->getStatusDistribution($apiConsumer->id, $startDate, $endDate),
                'hourly_distribution' => $analyticsService->getHourlyDistribution($apiConsumer->id),
            ],
        ]);
    }

    /**
     * Resolve the analytics window: an explicit start_date/end_date pair wins
     * (capped at the same 90-day span parseDays enforces), otherwise the
     * legacy relative `days` parameter.
     *
     * @return array{0: Carbon, 1: ?Carbon, 2: int}
     */
    private function resolveAnalyticsRange(Request $request, ApiConsumerAnalyticsService $analyticsService): array
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if (isset($validated['start_date'], $validated['end_date'])) {
            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate = Carbon::parse($validated['end_date'])->endOfDay();
            $days = (int) $startDate->diffInDays($endDate->copy()->startOfDay()) + 1;

            if ($days > 90) {
                throw ValidationException::withMessages([
                    'end_date' => 'The date range may not exceed 90 days.',
                ]);
            }

            return [$startDate, $endDate, $days];
        }

        $days = $analyticsService->parseDays((int) $request->input('days', 7));

        return [$analyticsService->getStartDate($days), null, $days];
    }

    /**
     * Get overall analytics for all API consumers
     */
    public function overallAnalytics(Request $request, ApiConsumerAnalyticsService $analyticsService): JsonResponse
    {
        $this->authorize('viewAny', ApiConsumer::class);

        [$startDate, $endDate, $days] = $this->resolveAnalyticsRange($request, $analyticsService);

        $summary = $analyticsService->getSummary(null, $startDate, includeConsumerCount: true, endDate: $endDate);
        $consumersWithRequests = $summary['active_consumers'] ?? 0;
        unset($summary['active_consumers']);

        $summary['total_consumers'] = ApiConsumer::count();
        $summary['active_consumers'] = ApiConsumer::active()->count();
        $summary['consumers_with_requests'] = $consumersWithRequests;

        return response()->json([
            'data' => [
                'period' => $analyticsService->buildPeriodData($days, $startDate, $endDate),
                'summary' => $summary,
                'requests_per_day' => $analyticsService->getRequestsPerDay(null, $startDate, $endDate),
                'top_consumers' => $analyticsService->getTopConsumers($startDate, endDate: $endDate),
                'status_distribution' => $analyticsService->getStatusDistribution(null, $startDate, $endDate),
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

        $restoredCount = ApiConsumer::onlyTrashed()->whereIn('id', $request->ids)->restore();

        if ($restoredCount > 0) {
            activity()
                ->causedBy(auth()->user())
                ->event('bulk_restored')
                ->withProperties([
                    'restored_count' => $restoredCount,
                    'model_type' => 'ApiConsumer',
                ])
                ->log("Bulk restored {$restoredCount} API consumer(s)");
        }

        return response()->json([
            'message' => $restoredCount.' API Consumer(s) restored successfully',
            'restored_count' => $restoredCount,
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

        $deletedCount = ApiConsumer::onlyTrashed()->whereIn('id', $request->ids)->forceDelete();

        if ($deletedCount > 0) {
            activity()
                ->causedBy(auth()->user())
                ->event('bulk_force_deleted')
                ->withProperties([
                    'deleted_count' => $deletedCount,
                    'model_type' => 'ApiConsumer',
                ])
                ->log("Permanently deleted {$deletedCount} API consumer(s)");
        }

        return response()->json([
            'message' => $deletedCount.' API Consumer(s) permanently deleted',
            'deleted_count' => $deletedCount,
        ]);
    }
}
