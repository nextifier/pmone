<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    public function __construct(
        protected AnalyticsDataFetcher $dataFetcher,
        protected AnalyticsAggregator $aggregator,
        protected SmartAnalyticsCache $cache
    ) {}

    /**
     * Get all active GA properties.
     */
    public function getActiveProperties(): Collection
    {
        return GaProperty::active()->get();
    }

    /**
     * Get property by ID.
     */
    public function getProperty(int $id): GaProperty
    {
        return GaProperty::findOrFail($id);
    }

    /**
     * Get property by property_id (GA4 property ID).
     */
    public function getPropertyByGA4Id(string $propertyId): ?GaProperty
    {
        return GaProperty::where('property_id', $propertyId)->first();
    }

    /**
     * Get analytics data for a single property.
     */
    public function getPropertyAnalytics(GaProperty $property, Period $period): array
    {
        $metricsData = $this->dataFetcher->fetchMetrics($property, $period);

        // Extract data from cache wrapper if present
        $metrics = $metricsData['data'] ?? $metricsData;

        // Calculate totals from rows if totals are empty
        if (empty($metrics['totals']) && ! empty($metrics['rows'])) {
            $metrics['totals'] = $this->calculateTotalsFromRows($metrics['rows']);
        }

        return [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'property_id' => $property->property_id,
                'last_synced_at' => $property->last_synced_at,
                'next_sync_at' => $property->next_sync_at,
            ],
            'metrics' => $metrics['totals'] ?? [],
            'rows' => $metrics['rows'] ?? [],
            'top_pages' => $this->dataFetcher->fetchTopPages($property, $period, 10),
            'traffic_sources' => $this->dataFetcher->fetchTrafficSources($property, $period),
            'devices' => $this->dataFetcher->fetchDevices($property, $period),
            'period' => [
                'start_date' => $period->startDate->format('Y-m-d'),
                'end_date' => $period->endDate->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Calculate totals from rows data.
     */
    protected function calculateTotalsFromRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $totals = [];
        $firstRow = reset($rows);

        // Initialize totals for each metric (excluding 'date')
        foreach (array_keys($firstRow) as $key) {
            if ($key !== 'date') {
                $totals[$key] = 0;
            }
        }

        // Sum up all values
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                if (isset($row[$key])) {
                    // For rates (bounceRate), calculate average instead of sum
                    if (str_contains($key, 'Rate')) {
                        continue; // We'll calculate average separately
                    }
                    $totals[$key] += $row[$key];
                }
            }
        }

        // Calculate average for rate metrics
        foreach (array_keys($firstRow) as $key) {
            if (str_contains($key, 'Rate')) {
                $sum = array_sum(array_column($rows, $key));
                $totals[$key] = count($rows) > 0 ? $sum / count($rows) : 0;
            }
        }

        return $totals;
    }

    /**
     * Get aggregated analytics from all active properties with cache-first strategy.
     */
    public function getAggregatedAnalytics(Period $period, ?array $propertyIds = null): array
    {
        // Create cache key for aggregate data
        $propertyIdsStr = $propertyIds ? implode(',', $propertyIds) : 'all';
        $cacheKey = "ga4_aggregate_{$propertyIdsStr}_{$period->startDate->format('Y-m-d')}_{$period->endDate->format('Y-m-d')}";
        $cacheTimestampKey = "{$cacheKey}_timestamp";

        // Get cached data and timestamp
        $cachedData = Cache::get($cacheKey);
        $cacheTimestamp = Cache::get($cacheTimestampKey);

        // CACHE-FIRST: Return cached data if available
        if ($cachedData) {
            $cacheAge = $cacheTimestamp ? now()->diffInMinutes($cacheTimestamp) : null;
            $isFresh = $cacheAge !== null && $cacheAge < 30;

            // Calculate next update
            $nextUpdate = $cacheTimestamp ? $cacheTimestamp->copy()->addMinutes(30) : null;
            $nextUpdateIn = $nextUpdate ? max(0, now()->diffInMinutes($nextUpdate, false)) : 0;

            // If cache is stale and not currently refreshing, dispatch background refresh
            if (!$isFresh && !Cache::has("{$cacheKey}_refreshing")) {
                $this->dispatchAggregateBackgroundRefresh($period, $propertyIds, $cacheKey);
            }

            return array_merge($cachedData, [
                'cache_info' => [
                    'last_updated' => $cacheTimestamp ? $cacheTimestamp->toIso8601String() : null,
                    'cache_age_minutes' => $cacheAge,
                    'next_update_in_minutes' => abs($nextUpdateIn),
                    'is_fresh' => $isFresh,
                    'is_updating' => !$isFresh,
                ],
            ]);
        }

        // No cache: fetch data synchronously
        $properties = $propertyIds
            ? GaProperty::active()->whereIn('property_id', $propertyIds)->get()
            : $this->getActiveProperties();

        $data = $this->aggregator->getDashboardData($properties, $period);

        // Cache the data
        Cache::put($cacheKey, $data, now()->addMinutes(30));
        Cache::put($cacheTimestampKey, now(), now()->addMinutes(30));

        return array_merge($data, [
            'cache_info' => [
                'last_updated' => now()->toIso8601String(),
                'cache_age_minutes' => 0,
                'next_update_in_minutes' => 30,
                'is_fresh' => true,
                'is_updating' => false,
            ],
        ]);
    }

    /**
     * Dispatch background job to refresh aggregate cache.
     */
    protected function dispatchAggregateBackgroundRefresh(Period $period, ?array $propertyIds, string $cacheKey): void
    {
        // Mark as refreshing
        Cache::put("{$cacheKey}_refreshing", true, now()->addMinutes(5));

        dispatch(function () use ($period, $propertyIds, $cacheKey) {
            try {
                $properties = $propertyIds
                    ? GaProperty::active()->whereIn('property_id', $propertyIds)->get()
                    : GaProperty::active()->get();

                $data = $this->aggregator->getDashboardData($properties, $period);

                Cache::put($cacheKey, $data, now()->addMinutes(30));
                Cache::put("{$cacheKey}_timestamp", now(), now()->addMinutes(30));
            } catch (\Exception $e) {
                \Log::error("Background aggregate cache refresh failed: {$e->getMessage()}");
            } finally {
                Cache::forget("{$cacheKey}_refreshing");
            }
        })->afterResponse();
    }

    /**
     * Sync property data and mark as synced.
     */
    public function syncProperty(GaProperty $property, Period $period): array
    {
        try {
            $data = $this->getPropertyAnalytics($property, $period);
            $property->markAsSynced();

            return [
                'success' => true,
                'property_id' => $property->property_id,
                'property_name' => $property->name,
                'synced_at' => $property->last_synced_at,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'property_id' => $property->property_id,
                'property_name' => $property->name,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync all active properties.
     */
    public function syncAllProperties(Period $period): array
    {
        $properties = $this->getActiveProperties();
        $results = [];

        foreach ($properties as $property) {
            $results[] = $this->syncProperty($property, $period);
        }

        $successful = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();

        return [
            'total' => $properties->count(),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Sync properties that need syncing based on their sync_frequency.
     */
    public function syncPropertiesNeedingUpdate(Period $period): array
    {
        $properties = GaProperty::needsSync()->get();
        $results = [];

        foreach ($properties as $property) {
            $results[] = $this->syncProperty($property, $period);
        }

        return [
            'properties_needing_sync' => $properties->count(),
            'results' => $results,
        ];
    }

    /**
     * Get cache status for all properties.
     */
    public function getCacheStatus(): array
    {
        $properties = $this->getActiveProperties();
        $status = [];

        foreach ($properties as $property) {
            $status[] = [
                'property_id' => $property->property_id,
                'property_name' => $property->name,
                'last_synced_at' => $property->last_synced_at,
                'needs_sync' => $property->needsSync(),
                'sync_frequency' => $property->sync_frequency,
            ];
        }

        return $status;
    }

    /**
     * Clear cache for specific property.
     */
    public function clearPropertyCache(GaProperty $property): void
    {
        $this->cache->clearPropertyCache($property);
    }

    /**
     * Clear cache for all properties.
     */
    public function clearAllCache(): void
    {
        $this->cache->clearAllCache();
    }

    /**
     * Create period from days count.
     */
    public function createPeriodFromDays(int $days): Period
    {
        return Period::days($days);
    }

    /**
     * Create period from date range.
     */
    public function createPeriodFromDates(string $startDate, string $endDate): Period
    {
        return Period::create(
            \Carbon\Carbon::parse($startDate),
            \Carbon\Carbon::parse($endDate)
        );
    }
}
