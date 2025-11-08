<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsCacheKeyGenerator as CacheKey;
use App\Services\GoogleAnalytics\Concerns\CalculatesTotalsFromRows;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    use CalculatesTotalsFromRows;

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
     * Get aggregated analytics from all active properties with cache-first strategy.
     * CRITICAL: Always return cache immediately if available, NEVER wait for fresh data.
     */
    public function getAggregatedAnalytics(Period $period, ?array $propertyIds = null): array
    {
        // Create cache key for aggregate data using centralized generator
        $cacheKey = CacheKey::forAggregate($propertyIds, $period->startDate, $period->endDate);
        $cacheTimestampKey = CacheKey::timestamp($cacheKey);
        $lastSuccessKey = CacheKey::lastSuccess($cacheKey);

        \Log::info('Fetching aggregate analytics', [
            'cache_key' => $cacheKey,
            'last_success_key' => $lastSuccessKey,
        ]);

        // Get cached data and timestamp
        $cachedData = Cache::get($cacheKey);
        $cacheTimestamp = Cache::get($cacheTimestampKey);
        $lastSuccessData = Cache::get($lastSuccessKey); // Last known good data

        \Log::info('Cache status', [
            'has_cached_data' => ! is_null($cachedData),
            'has_last_success' => ! is_null($lastSuccessData),
            'cache_timestamp' => $cacheTimestamp,
        ]);

        // CACHE-FIRST PRIORITY 1: Return fresh cache if available
        if ($cachedData) {
            $cacheAge = $cacheTimestamp ? abs(now()->diffInMinutes($cacheTimestamp, false)) : null;
            $isFresh = $cacheAge !== null && $cacheAge < 30;

            // Calculate next update
            $nextUpdate = $cacheTimestamp ? $cacheTimestamp->copy()->addMinutes(30) : null;
            $nextUpdateIn = $nextUpdate ? max(0, now()->diffInMinutes($nextUpdate, false)) : 0;

            // Get last_synced_at from most recent property
            $lastSyncedAt = $this->getMostRecentSyncTime($propertyIds);

            // If cache is stale and not currently refreshing, dispatch background refresh
            if (! $isFresh && ! Cache::has(CacheKey::refreshing($cacheKey))) {
                \Log::info('Cache is stale, dispatching background refresh');
                $this->dispatchAggregateBackgroundRefresh($period, $propertyIds, $cacheKey);
            }

            return array_merge($cachedData, [
                'cache_info' => [
                    'last_updated' => $cacheTimestamp ? $cacheTimestamp->toIso8601String() : ($lastSyncedAt ? $lastSyncedAt->toIso8601String() : null),
                    'cache_age_minutes' => $cacheAge, // Use actual cache age, not property sync time
                    'next_update_in_minutes' => abs($nextUpdateIn),
                    'is_fresh' => $isFresh,
                    'is_updating' => ! $isFresh,
                ],
            ]);
        }

        // CACHE-FIRST PRIORITY 2: Return last known good data if exists (even if very old)
        if ($lastSuccessData) {
            \Log::info('Using last_success fallback cache');

            // Dispatch background refresh to get new data
            if (! Cache::has(CacheKey::refreshing($cacheKey))) {
                \Log::info('Dispatching background refresh from fallback');
                $this->dispatchAggregateBackgroundRefresh($period, $propertyIds, $cacheKey);
            }

            // When using fallback during refresh, show minimal cache age since we're actively updating
            // This prevents showing confusing large numbers like "16 hours ago" when we're actually refreshing now
            $cacheAge = 0; // Show as "just now" since we're using fallback during active refresh

            return array_merge($lastSuccessData, [
                'cache_info' => [
                    'last_updated' => now()->toIso8601String(),
                    'cache_age_minutes' => $cacheAge,
                    'next_update_in_minutes' => 0, // Updating now
                    'is_fresh' => false,
                    'is_updating' => true,
                    'from_fallback' => true,
                ],
            ]);
        }

        // PRIORITY 3: NO CACHE AT ALL - Return empty and dispatch background job
        \Log::warning('No cache found, dispatching background job and returning empty data');

        // Dispatch background job to fetch data
        if (! Cache::has(CacheKey::refreshing($cacheKey))) {
            $this->dispatchAggregateBackgroundRefresh($period, $propertyIds, $cacheKey);
        }

        return $this->getEmptyDataStructure($period, true);
    }

    /**
     * Get empty data structure.
     */
    protected function getEmptyDataStructure(Period $period, bool $isUpdating = false, ?string $error = null): array
    {
        return [
            'totals' => [
                'activeUsers' => 0,
                'newUsers' => 0,
                'sessions' => 0,
                'screenPageViews' => 0,
                'bounceRate' => 0,
                'averageSessionDuration' => 0,
            ],
            'property_breakdown' => [],
            'successful_fetches' => 0,
            'top_pages' => [],
            'traffic_sources' => [],
            'devices' => [],
            'period' => [
                'start_date' => $period->startDate->format('Y-m-d'),
                'end_date' => $period->endDate->format('Y-m-d'),
            ],
            'properties_count' => 0,
            'cache_info' => [
                'last_updated' => null,
                'cache_age_minutes' => null,
                'next_update_in_minutes' => 0,
                'is_fresh' => false,
                'is_updating' => $isUpdating,
                'initial_load' => true,
                'error' => $error,
            ],
        ];
    }

    /**
     * Get most recent sync time from properties.
     */
    protected function getMostRecentSyncTime(?array $propertyIds): ?\Carbon\Carbon
    {
        $query = GaProperty::query();

        if ($propertyIds) {
            $query->whereIn('property_id', $propertyIds);
        }

        $mostRecent = $query->whereNotNull('last_synced_at')
            ->orderBy('last_synced_at', 'desc')
            ->first();

        return $mostRecent?->last_synced_at;
    }

    /**
     * Dispatch background job to refresh aggregate cache.
     */
    protected function dispatchAggregateBackgroundRefresh(Period $period, ?array $propertyIds, string $cacheKey): void
    {
        // Mark as refreshing
        $refreshingKey = CacheKey::refreshing($cacheKey);
        Cache::put($refreshingKey, true, now()->addMinutes(5));

        // Bind aggregator to avoid context issues in closure
        $aggregator = $this->aggregator;

        // Calculate days for sync log
        $days = $period->startDate->diffInDays($period->endDate) + 1;

        dispatch(function () use ($period, $propertyIds, $cacheKey, $aggregator, $days) {
            // Create sync log entry for aggregate dashboard sync
            $syncLog = \App\Models\AnalyticsSyncLog::startSync(
                syncType: 'aggregate',
                gaPropertyId: null, // null for aggregate syncs
                days: $days
            );

            try {
                \Log::info("Background refresh started for {$cacheKey}", [
                    'sync_log_id' => $syncLog->id,
                ]);

                $properties = $propertyIds
                    ? GaProperty::active()->whereIn('property_id', $propertyIds)->get()
                    : GaProperty::active()->get();

                if ($properties->isEmpty()) {
                    \Log::warning('No active properties found for background refresh');
                    $syncLog->markFailed('No active properties found');

                    return;
                }

                \Log::info("Fetching dashboard data for {$properties->count()} properties");

                $data = $aggregator->getDashboardData($properties, $period);

                // Store with 30-minute expiry
                Cache::put($cacheKey, $data, now()->addMinutes(30));
                Cache::put("{$cacheKey}_timestamp", now(), now()->addMinutes(30));

                // Store as "last known good data" that never expires (for instant fallback)
                $lastSuccessKey = "{$cacheKey}_last_success";
                Cache::put($lastSuccessKey, $data, now()->addYears(10));

                // Mark sync as successful with metadata
                $syncLog->markSuccess([
                    'properties_count' => $properties->count(),
                    'has_totals' => ! empty($data['totals'] ?? []),
                    'cache_key' => $cacheKey,
                ]);

                \Log::info('Aggregate cache refreshed successfully', [
                    'cache_key' => $cacheKey,
                    'last_success_key' => $lastSuccessKey,
                    'properties_count' => $properties->count(),
                    'has_totals' => ! empty($data['totals'] ?? []),
                    'sync_log_id' => $syncLog->id,
                ]);
            } catch (\Exception $e) {
                // Mark sync as failed
                $syncLog->markFailed($e->getMessage(), [
                    'cache_key' => $cacheKey,
                    'exception_class' => get_class($e),
                ]);

                \Log::error('Background aggregate cache refresh failed', [
                    'cache_key' => $cacheKey,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'sync_log_id' => $syncLog->id,
                ]);
            } finally {
                Cache::forget($refreshingKey);
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
