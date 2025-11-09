<?php

namespace App\Http\Controllers\Api;

use App\Exports\GaPropertiesExport;
use App\Exports\GaPropertiesTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleAnalytics\GetAnalyticsRequest;
use App\Http\Requests\GoogleAnalytics\StoreGaPropertyRequest;
use App\Http\Requests\GoogleAnalytics\SyncAnalyticsRequest;
use App\Http\Requests\GoogleAnalytics\TriggerSyncRequest;
use App\Http\Requests\GoogleAnalytics\UpdateGaPropertyRequest;
use App\Imports\GaPropertiesImport;
use App\Jobs\AggregateAnalyticsData;
use App\Jobs\SyncGoogleAnalyticsData;
use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsCacheKeyGenerator as CacheKey;
use App\Services\GoogleAnalytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GoogleAnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected \App\Services\GoogleAnalytics\AnalyticsAggregator $aggregator
    ) {}

    /**
     * Get list of all GA properties with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GaProperty::query();

        // Apply search filter
        if ($request->has('filter.search')) {
            $search = $request->input('filter.search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('property_id', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->has('filter.status')) {
            $statuses = explode(',', $request->input('filter.status'));
            if (in_array('active', $statuses) && ! in_array('inactive', $statuses)) {
                $query->where('is_active', true);
            } elseif (in_array('inactive', $statuses) && ! in_array('active', $statuses)) {
                $query->where('is_active', false);
            }
        }

        // Apply sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = 'asc';

        if (str_starts_with($sortField, '-')) {
            $sortField = substr($sortField, 1);
            $sortDirection = 'desc';
        }

        $query->orderBy($sortField, $sortDirection);

        // Check if client-only mode
        if ($request->has('client_only')) {
            $data = $query->with(['tags', 'project.media'])->get()->map(function ($property) {
                $propertyData = array_merge($property->toArray(), [
                    'next_sync_at' => $property->next_sync_at,
                    // Ensure last_synced_at is properly serialized as ISO8601 string
                    'last_synced_at' => $property->last_synced_at?->toIso8601String(),
                ]);

                // Simplify tags to just names
                $propertyData['tags'] = $property->tags->pluck('name')->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name)->values()->toArray();

                // Add profile_image URLs if project has media
                if ($property->project) {
                    $propertyData['project']['profile_image'] = $property->project->getMediaUrls('profile_image');
                    // Remove media collection from response
                    unset($propertyData['project']['media']);
                }

                return $propertyData;
            });

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $data->count(),
                    'total' => $data->count(),
                ],
            ]);
        }

        // Server-side pagination
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $properties = $query->with(['tags', 'project.media'])->paginate($perPage, ['*'], 'page', $page);

        $data = collect($properties->items())->map(function ($property) {
            $propertyData = array_merge($property->toArray(), [
                'next_sync_at' => $property->next_sync_at,
            ]);

            // Simplify tags to just names
            $propertyData['tags'] = $property->tags->pluck('name')->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name)->values()->toArray();

            // Add profile_image URLs if project has media
            if ($property->project) {
                $propertyData['project']['profile_image'] = $property->project->getMediaUrls('profile_image');
                // Remove media collection from response
                unset($propertyData['project']['media']);
            }

            return $propertyData;
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ],
        ]);
    }

    /**
     * Get single GA property.
     */
    public function show(int $id): JsonResponse
    {
        $property = GaProperty::with(['tags', 'project.media'])->findOrFail($id);

        $propertyData = array_merge($property->toArray(), [
            'next_sync_at' => $property->next_sync_at,
        ]);

        // Simplify tags to just names
        $propertyData['tags'] = $property->tags->pluck('name')->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name)->values()->toArray();

        // Add profile_image URLs if project has media
        if ($property->project) {
            $propertyData['project']['profile_image'] = $property->project->getMediaUrls('profile_image');
            // Remove media collection from response
            unset($propertyData['project']['media']);
        }

        return response()->json([
            'data' => $propertyData,
        ]);
    }

    /**
     * Create new GA property.
     */
    public function store(StoreGaPropertyRequest $request): JsonResponse
    {
        $property = GaProperty::create([
            'project_id' => $request->input('project_id'),
            'name' => $request->input('name'),
            'property_id' => $request->input('property_id'),
            'is_active' => $request->input('is_active', true),
            'sync_frequency' => $request->input('sync_frequency', 10),
        ]);

        // Sync tags if provided
        if ($request->has('tags')) {
            $property->syncTags($request->input('tags'));
        }

        // Load tags relationship for response
        $property->load(['tags', 'project.media']);

        $propertyData = $property->toArray();

        // Simplify tags to just names
        $propertyData['tags'] = $property->tags->pluck('name')->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name)->values()->toArray();

        // Add profile_image URLs if project has media
        if ($property->project) {
            $propertyData['project']['profile_image'] = $property->project->getMediaUrls('profile_image');
            // Remove media collection from response
            unset($propertyData['project']['media']);
        }

        return response()->json([
            'message' => 'GA property created successfully',
            'data' => $propertyData,
        ], 201);
    }

    /**
     * Update GA property.
     */
    public function update(UpdateGaPropertyRequest $request, int $id): JsonResponse
    {
        $property = GaProperty::findOrFail($id);

        $property->update($request->only([
            'project_id',
            'name',
            'property_id',
            'is_active',
            'sync_frequency',
        ]));

        // Sync tags if provided
        if ($request->has('tags')) {
            $property->syncTags($request->input('tags'));
        }

        // Load tags relationship for response
        $property->load(['tags', 'project.media']);

        $propertyData = $property->toArray();

        // Simplify tags to just names
        $propertyData['tags'] = $property->tags->pluck('name')->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name)->values()->toArray();

        // Add profile_image URLs if project has media
        if ($property->project) {
            $propertyData['project']['profile_image'] = $property->project->getMediaUrls('profile_image');
            // Remove media collection from response
            unset($propertyData['project']['media']);
        }

        return response()->json([
            'message' => 'GA property updated successfully',
            'data' => $propertyData,
        ]);
    }

    /**
     * Delete GA property.
     */
    public function destroy(int $id): JsonResponse
    {
        $property = GaProperty::findOrFail($id);

        $property->delete();

        return response()->json([
            'message' => 'GA property deleted successfully',
        ]);
    }

    /**
     * Get list of all GA properties (simple list without pagination).
     */
    public function getProperties(): JsonResponse
    {
        $properties = GaProperty::with(['tags', 'project.media'])->get();

        $data = $properties->map(function ($property) {
            $propertyData = $property->toArray();

            // Simplify tags to just names
            $propertyData['tags'] = $property->tags->pluck('name')->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name)->values()->toArray();

            // Add profile_image URLs if project has media
            if ($property->project) {
                $propertyData['project']['profile_image'] = $property->project->getMediaUrls('profile_image');
                // Remove media collection from response
                unset($propertyData['project']['media']);
            }

            return $propertyData;
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get analytics for a single property.
     */
    public function getPropertyAnalytics(GetAnalyticsRequest $request, string $id): JsonResponse
    {
        // Increase execution time for API requests
        set_time_limit(120);

        // Find property by property_id (Google Analytics Property ID), not database id
        $property = GaProperty::where('property_id', $id)->firstOrFail();

        $period = $this->createPeriodFromRequest($request);

        $analytics = $this->analyticsService->getPropertyAnalytics($property, $period);

        return response()->json([
            'data' => $analytics,
        ]);
    }

    /**
     * Get aggregated analytics from multiple/all properties.
     */
    public function getAggregatedAnalytics(GetAnalyticsRequest $request): JsonResponse
    {
        // Increase execution time for API requests
        set_time_limit(120);

        $period = $this->createPeriodFromRequest($request);
        $propertyIds = $request->input('property_ids');

        $analytics = $this->analyticsService->getAggregatedAnalytics($period, $propertyIds);

        return response()->json($analytics);
    }

    /**
     * Trigger manual sync for a property or all properties.
     */
    public function sync(SyncAnalyticsRequest $request): JsonResponse
    {
        $propertyId = $request->input('property_id');
        $days = $request->input('days', 7);

        if ($propertyId) {
            // Sync single property
            $property = GaProperty::findOrFail($propertyId);

            SyncGoogleAnalyticsData::dispatch($property->id, $days);

            return response()->json([
                'message' => 'Sync job dispatched for property: '.$property->name,
                'property_id' => $property->property_id,
                'property_name' => $property->name,
            ]);
        }

        // Sync all active properties
        $properties = GaProperty::active()->get();

        foreach ($properties as $property) {
            SyncGoogleAnalyticsData::dispatch($property->id, $days);
        }

        return response()->json([
            'message' => 'Sync jobs dispatched for '.$properties->count().' properties',
            'properties_count' => $properties->count(),
        ]);
    }

    /**
     * Trigger aggregation job.
     */
    public function aggregate(GetAnalyticsRequest $request): JsonResponse
    {
        $propertyIds = $request->input('property_ids');
        $days = $request->input('days', 7);

        AggregateAnalyticsData::dispatch($propertyIds, $days);

        return response()->json([
            'message' => 'Aggregation job dispatched',
            'property_ids' => $propertyIds,
            'days' => $days,
        ]);
    }

    /**
     * Trigger manual aggregate sync now for testing/debugging.
     * This forces cache refresh and creates sync log entries immediately.
     * Runs SYNCHRONOUSLY to ensure sync logs are created for testing.
     */
    public function triggerAggregateSyncNow(TriggerSyncRequest $request): JsonResponse
    {
        // Rate limiting: 2 syncs per hour per user
        $userId = auth()->id();
        $key = "sync-analytics:{$userId}";

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 2)) {
            $retryAfter = \Illuminate\Support\Facades\RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many sync attempts. Please try again later.',
                'retry_after_seconds' => $retryAfter,
                'retry_after_minutes' => ceil($retryAfter / 60),
            ], 429);
        }

        \Illuminate\Support\Facades\RateLimiter::hit($key, 3600); // 1 hour

        $days = $request->validated()['days'];

        try {
            // Create period
            $period = $this->analyticsService->createPeriodFromDays($days);

            // Create sync log entry
            $syncLog = \App\Models\AnalyticsSyncLog::startSync(
                syncType: 'aggregate',
                gaPropertyId: null,
                days: $days
            );

            // Get all active properties
            $properties = \App\Models\GaProperty::active()->get();

            if ($properties->isEmpty()) {
                $syncLog->markFailed('No active properties found');

                return response()->json([
                    'message' => 'No active properties found',
                    'sync_log_id' => $syncLog->id,
                ], 400);
            }

            // Clear cache to force refresh using cache key generator
            $cacheKey = CacheKey::forAggregate(null, $period->startDate, $period->endDate);

            // Clear all related cache keys
            foreach (CacheKey::getAllKeys($cacheKey) as $key) {
                \Illuminate\Support\Facades\Cache::forget($key);
            }

            // Fetch data DIRECTLY without triggering background job
            \Log::info('Manual sync: Fetching dashboard data', [
                'sync_log_id' => $syncLog->id,
                'properties_count' => $properties->count(),
            ]);

            $data = $this->aggregator->getDashboardData($properties, $period);

            // Store in cache using proper cache keys
            \Illuminate\Support\Facades\Cache::put($cacheKey, $data, now()->addMinutes(30));
            \Illuminate\Support\Facades\Cache::put(CacheKey::timestamp($cacheKey), now(), now()->addMinutes(30));
            \Illuminate\Support\Facades\Cache::put(CacheKey::lastSuccess($cacheKey), $data, now()->addYears(10));

            // Mark sync as successful
            $syncLog->markSuccess([
                'properties_count' => $properties->count(),
                'has_data' => ! empty($data['totals'] ?? []),
                'cache_key' => $cacheKey,
            ]);

            \Log::info('Manual sync completed', [
                'sync_log_id' => $syncLog->id,
                'properties_count' => $properties->count(),
                'has_totals' => ! empty($data['totals'] ?? []),
            ]);

            return response()->json([
                'message' => 'Aggregate sync completed successfully',
                'days' => $days,
                'properties_count' => $properties->count(),
                'sync_log_id' => $syncLog->id,
                'has_data' => ! empty($data['totals'] ?? []),
            ]);
        } catch (\Exception $e) {
            if (isset($syncLog)) {
                $syncLog->markFailed($e->getMessage());
            }

            \Log::error('Manual sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sync_log_id' => $syncLog->id ?? null,
            ]);

            return response()->json([
                'message' => 'Sync failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
                'sync_log_id' => $syncLog->id ?? null,
            ], 500);
        }
    }

    /**
     * Get cache status for all properties.
     */
    public function getCacheStatus(): JsonResponse
    {
        $status = $this->analyticsService->getCacheStatus();

        return response()->json([
            'data' => $status,
        ]);
    }

    /**
     * Clear cache for a property.
     */
    public function clearPropertyCache(string $id): JsonResponse
    {
        // Find property by property_id (Google Analytics Property ID), not database id
        $property = GaProperty::where('property_id', $id)->firstOrFail();

        $this->analyticsService->clearPropertyCache($property);

        return response()->json([
            'message' => 'Cache cleared for property: '.$property->name,
            'property_id' => $property->property_id,
        ]);
    }

    /**
     * Clear all analytics cache.
     */
    public function clearAllCache(): JsonResponse
    {
        $this->analyticsService->clearAllCache();

        return response()->json([
            'message' => 'All analytics cache cleared',
        ]);
    }

    /**
     * Get realtime active users (last 30 minutes).
     */
    public function getRealtimeActiveUsers(Request $request): JsonResponse
    {
        $propertyIds = $request->input('property_ids');

        $data = $this->analyticsService->getRealtimeActiveUsers($propertyIds);

        return response()->json($data);
    }

    /**
     * Create period from request parameters.
     */
    protected function createPeriodFromRequest(GetAnalyticsRequest $request)
    {
        if ($request->has('start_date') && $request->has('end_date')) {
            return $this->analyticsService->createPeriodFromDates(
                $request->input('start_date'),
                $request->input('end_date')
            );
        }

        $days = $request->input('days', 7);

        return $this->analyticsService->createPeriodFromDays($days);
    }

    // Import/Export

    /**
     * Export GA properties to Excel.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }
        if ($status = $request->input('filter_status')) {
            $filters['status'] = $status;
        }

        $sort = $request->input('sort', '-last_synced_at');

        $export = new GaPropertiesExport($filters, $sort);

        $filename = 'ga_properties_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Download import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new GaPropertiesTemplateExport, 'ga_properties_import_template.xlsx');
    }

    /**
     * Import GA properties from Excel.
     */
    public function import(Request $request): JsonResponse
    {
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

            // Import GA properties
            $import = new GaPropertiesImport;
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
                'message' => 'GA properties imported successfully',
                'imported_count' => $importedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('GA properties import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import GA properties',
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
     * Handle temporary upload for media.
     */
    private function handleTemporaryUpload(Request $request, GaProperty $property, string $fieldName, string $collection): void
    {
        // Check for delete flag first
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $property->clearMediaCollection($collection);

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
        $property->clearMediaCollection($collection);

        // Add new media
        $property->addMedia(\Illuminate\Support\Facades\Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        // Clean up temporary files
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }
}
