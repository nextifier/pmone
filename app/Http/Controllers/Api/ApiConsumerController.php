<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiConsumerRequest;
use App\Http\Requests\UpdateApiConsumerRequest;
use App\Http\Resources\ApiConsumerResource;
use App\Models\ApiConsumer;
use App\Models\ApiConsumerRequest as ApiRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Get analytics data for the specified consumer
     */
    public function analytics(Request $request, ApiConsumer $apiConsumer): JsonResponse
    {
        $this->authorize('view', $apiConsumer);

        $days = (int) $request->input('days', 7);
        $days = min(max($days, 1), 90); // Limit between 1-90 days

        $startDate = now()->subDays($days)->startOfDay();

        // Get summary statistics
        $summary = ApiRequest::query()
            ->where('api_consumer_id', $apiConsumer->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as successful_requests,
                COUNT(CASE WHEN status_code >= 400 THEN 1 END) as failed_requests,
                AVG(response_time_ms) as avg_response_time,
                MAX(response_time_ms) as max_response_time,
                MIN(response_time_ms) as min_response_time
            ')
            ->first();

        // Get requests per day for chart
        $requestsPerDay = ApiRequest::query()
            ->where('api_consumer_id', $apiConsumer->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
            ]);

        // Get top endpoints
        $topEndpoints = ApiRequest::query()
            ->where('api_consumer_id', $apiConsumer->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('endpoint, method, COUNT(*) as count, AVG(response_time_ms) as avg_time')
            ->groupBy('endpoint', 'method')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'endpoint' => $item->endpoint,
                'method' => $item->method,
                'count' => (int) $item->count,
                'avg_time' => round($item->avg_time, 2),
            ]);

        // Get status code distribution
        $statusDistribution = ApiRequest::query()
            ->where('api_consumer_id', $apiConsumer->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                CASE
                    WHEN status_code >= 200 AND status_code < 300 THEN "2xx"
                    WHEN status_code >= 300 AND status_code < 400 THEN "3xx"
                    WHEN status_code >= 400 AND status_code < 500 THEN "4xx"
                    WHEN status_code >= 500 THEN "5xx"
                    ELSE "unknown"
                END as status_group,
                COUNT(*) as count
            ')
            ->groupBy('status_group')
            ->get()
            ->pluck('count', 'status_group');

        // Get hourly distribution for today
        $hourlyDistribution = ApiRequest::query()
            ->where('api_consumer_id', $apiConsumer->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn ($item) => [
                'hour' => (int) $item->hour,
                'count' => (int) $item->count,
            ]);

        return response()->json([
            'data' => [
                'consumer' => new ApiConsumerResource($apiConsumer),
                'period' => [
                    'days' => $days,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => now()->toDateString(),
                ],
                'summary' => [
                    'total_requests' => (int) ($summary->total_requests ?? 0),
                    'successful_requests' => (int) ($summary->successful_requests ?? 0),
                    'failed_requests' => (int) ($summary->failed_requests ?? 0),
                    'success_rate' => $summary->total_requests > 0
                        ? round(($summary->successful_requests / $summary->total_requests) * 100, 2)
                        : 0,
                    'avg_response_time' => round($summary->avg_response_time ?? 0, 2),
                    'max_response_time' => (int) ($summary->max_response_time ?? 0),
                    'min_response_time' => (int) ($summary->min_response_time ?? 0),
                ],
                'requests_per_day' => $requestsPerDay,
                'top_endpoints' => $topEndpoints,
                'status_distribution' => [
                    '2xx' => (int) ($statusDistribution['2xx'] ?? 0),
                    '3xx' => (int) ($statusDistribution['3xx'] ?? 0),
                    '4xx' => (int) ($statusDistribution['4xx'] ?? 0),
                    '5xx' => (int) ($statusDistribution['5xx'] ?? 0),
                ],
                'hourly_distribution' => $hourlyDistribution,
            ],
        ]);
    }

    /**
     * Get overall analytics for all API consumers
     */
    public function overallAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApiConsumer::class);

        $days = (int) $request->input('days', 7);
        $days = min(max($days, 1), 90);

        $startDate = now()->subDays($days)->startOfDay();

        // Get summary statistics across all consumers
        $summary = ApiRequest::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_requests,
                COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as successful_requests,
                COUNT(CASE WHEN status_code >= 400 THEN 1 END) as failed_requests,
                AVG(response_time_ms) as avg_response_time,
                COUNT(DISTINCT api_consumer_id) as active_consumers
            ')
            ->first();

        // Get total consumers
        $totalConsumers = ApiConsumer::count();
        $activeConsumers = ApiConsumer::active()->count();

        // Get requests per day for chart
        $requestsPerDay = ApiRequest::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
            ]);

        // Get top consumers by request count
        $topConsumers = ApiRequest::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('api_consumer_id, COUNT(*) as request_count, AVG(response_time_ms) as avg_time')
            ->groupBy('api_consumer_id')
            ->orderByDesc('request_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $consumer = ApiConsumer::find($item->api_consumer_id);

                return [
                    'id' => $item->api_consumer_id,
                    'name' => $consumer?->name ?? 'Unknown',
                    'website_url' => $consumer?->website_url ?? '',
                    'request_count' => (int) $item->request_count,
                    'avg_time' => round($item->avg_time, 2),
                ];
            });

        // Get status distribution
        $statusDistribution = ApiRequest::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                CASE
                    WHEN status_code >= 200 AND status_code < 300 THEN "2xx"
                    WHEN status_code >= 300 AND status_code < 400 THEN "3xx"
                    WHEN status_code >= 400 AND status_code < 500 THEN "4xx"
                    WHEN status_code >= 500 THEN "5xx"
                    ELSE "unknown"
                END as status_group,
                COUNT(*) as count
            ')
            ->groupBy('status_group')
            ->get()
            ->pluck('count', 'status_group');

        return response()->json([
            'data' => [
                'period' => [
                    'days' => $days,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => now()->toDateString(),
                ],
                'summary' => [
                    'total_requests' => (int) ($summary->total_requests ?? 0),
                    'successful_requests' => (int) ($summary->successful_requests ?? 0),
                    'failed_requests' => (int) ($summary->failed_requests ?? 0),
                    'success_rate' => $summary->total_requests > 0
                        ? round(($summary->successful_requests / $summary->total_requests) * 100, 2)
                        : 0,
                    'avg_response_time' => round($summary->avg_response_time ?? 0, 2),
                    'total_consumers' => $totalConsumers,
                    'active_consumers' => $activeConsumers,
                    'consumers_with_requests' => (int) ($summary->active_consumers ?? 0),
                ],
                'requests_per_day' => $requestsPerDay,
                'top_consumers' => $topConsumers,
                'status_distribution' => [
                    '2xx' => (int) ($statusDistribution['2xx'] ?? 0),
                    '3xx' => (int) ($statusDistribution['3xx'] ?? 0),
                    '4xx' => (int) ($statusDistribution['4xx'] ?? 0),
                    '5xx' => (int) ($statusDistribution['5xx'] ?? 0),
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
