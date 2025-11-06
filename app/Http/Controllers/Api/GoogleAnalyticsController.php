<?php

namespace App\Http\Controllers\Api;

use App\Exports\GaPropertiesExport;
use App\Exports\GaPropertiesTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleAnalytics\GetAnalyticsRequest;
use App\Http\Requests\GoogleAnalytics\StoreGaPropertyRequest;
use App\Http\Requests\GoogleAnalytics\SyncAnalyticsRequest;
use App\Http\Requests\GoogleAnalytics\UpdateGaPropertyRequest;
use App\Imports\GaPropertiesImport;
use App\Jobs\AggregateAnalyticsData;
use App\Jobs\SyncGoogleAnalyticsData;
use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GoogleAnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
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
                    ->orWhere('property_id', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%");
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
            return response()->json([
                'data' => $query->get(),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $query->count(),
                    'total' => $query->count(),
                ],
            ]);
        }

        // Server-side pagination
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $properties = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $properties->items(),
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
        $property = GaProperty::findOrFail($id);

        return response()->json([
            'data' => $property,
        ]);
    }

    /**
     * Create new GA property.
     */
    public function store(StoreGaPropertyRequest $request): JsonResponse
    {
        $property = GaProperty::create([
            'name' => $request->input('name'),
            'property_id' => $request->input('property_id'),
            'account_name' => $request->input('account_name'),
            'is_active' => $request->input('is_active', true),
            'sync_frequency' => $request->input('sync_frequency', 10),
            'rate_limit_per_hour' => $request->input('rate_limit_per_hour', 12),
        ]);

        return response()->json([
            'message' => 'GA property created successfully',
            'data' => $property,
        ], 201);
    }

    /**
     * Update GA property.
     */
    public function update(UpdateGaPropertyRequest $request, int $id): JsonResponse
    {
        $property = GaProperty::findOrFail($id);

        $property->update($request->only([
            'name',
            'property_id',
            'account_name',
            'is_active',
            'sync_frequency',
            'rate_limit_per_hour',
        ]));

        return response()->json([
            'message' => 'GA property updated successfully',
            'data' => $property,
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
        $properties = GaProperty::all();

        return response()->json([
            'data' => $properties,
        ]);
    }

    /**
     * Get properties grouped by account.
     */
    public function getPropertiesByAccount(): JsonResponse
    {
        $grouped = $this->analyticsService->getGroupedByAccount();

        return response()->json([
            'data' => $grouped,
        ]);
    }

    /**
     * Get analytics for a single property.
     */
    public function getPropertyAnalytics(GetAnalyticsRequest $request, int $id): JsonResponse
    {
        // Increase execution time for API requests
        set_time_limit(120);

        $property = GaProperty::findOrFail($id);

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

        return response()->json([
            'data' => $analytics,
        ]);
    }

    /**
     * Get analytics for a specific account.
     */
    public function getAccountAnalytics(GetAnalyticsRequest $request, string $accountName): JsonResponse
    {
        $period = $this->createPeriodFromRequest($request);

        $analytics = $this->analyticsService->getAnalyticsByAccount($accountName, $period);

        return response()->json([
            'data' => $analytics,
        ]);
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
    public function clearPropertyCache(int $id): JsonResponse
    {
        $property = GaProperty::findOrFail($id);

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
}
