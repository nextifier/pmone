<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleAnalytics\GetAnalyticsRequest;
use App\Http\Requests\GoogleAnalytics\SyncAnalyticsRequest;
use App\Jobs\AggregateAnalyticsData;
use App\Jobs\SyncGoogleAnalyticsData;
use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleAnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Get list of all GA properties.
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
}
