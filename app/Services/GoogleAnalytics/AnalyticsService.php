<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsCacheKeyGenerator as CacheKey;
use App\Services\GoogleAnalytics\Concerns\CalculatesTotalsFromRows;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Main Google Analytics Service.
 *
 * Handles fetching, aggregating, and caching analytics data from Google Analytics 4.
 * Implements cache-first strategy with background refresh for optimal performance.
 *
 * Key Features:
 * - Cache-first data retrieval minimizes API calls
 * - Smart chunking for large datasets (>100 properties)
 * - Parallel data fetching for improved performance
 * - Automatic background refresh when cache is stale
 * - Realtime active users tracking
 *
 * Performance Optimizations:
 * - N+1 query prevention with indexed lookups
 * - Parallel execution for multi-property aggregation
 * - Configurable chunking thresholds
 * - Client connection pooling for GA4 API
 *
 * @see config/analytics.php for configuration options
 */
class AnalyticsService
{
    use CalculatesTotalsFromRows;

    public function __construct(
        protected AnalyticsDataFetcher $dataFetcher,
        protected AnalyticsAggregator $aggregator,
        protected DailyDataAggregator $dailyAggregator,
        protected SmartAnalyticsCache $cache,
        protected AnalyticsMetrics $metrics
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
     * NOW USING DAILY AGGREGATION: Fetches from 365-day cache and filters by period.
     */
    public function getPropertyAnalytics(GaProperty $property, Period $period): array
    {
        // Load project relationship if not already loaded
        if (! $property->relationLoaded('project')) {
            $property->load('project');
        }

        // Use daily aggregator to get data from 365-day cache
        $metricsData = $this->dailyAggregator->getDataForPeriod($property, $period);

        // Extract metrics from daily aggregator response
        $metrics = $metricsData['data'] ?? $metricsData;

        // Calculate totals from rows if totals are empty
        if (empty($metrics['totals']) && ! empty($metrics['rows'])) {
            $metrics['totals'] = $this->calculateTotalsFromRows($metrics['rows']);
        }

        // Build property data
        $propertyData = [
            'id' => $property->id,
            'name' => $property->name,
            'property_id' => $property->property_id,
            'last_synced_at' => $property->last_synced_at,
            'next_sync_at' => $property->next_sync_at,
        ];

        // Add project data if available
        if ($property->project) {
            $propertyData['project'] = [
                'id' => $property->project->id,
                'name' => $property->project->name,
                'profile_image' => $property->project->profile_image,
            ];
        }

        return [
            'property' => $propertyData,
            'metrics' => $metrics['totals'] ?? [],
            'rows' => $metrics['rows'] ?? [],
            'top_pages' => $this->dailyAggregator->getTopPagesForPeriod($property, $period, 20),
            'traffic_sources' => $this->dailyAggregator->getTrafficSourcesForPeriod($property, $period),
            'devices' => $this->dailyAggregator->getDevicesForPeriod($property, $period),
            'period' => [
                'start_date' => $period->startDate->format('Y-m-d'),
                'end_date' => $period->endDate->format('Y-m-d'),
            ],
            'is_fresh' => $metricsData['is_fresh'] ?? false,
            'cached_at' => $metricsData['cached_at'] ?? null,
        ];
    }

    /**
     * Calculate dynamic cache TTL based on period length.
     * - Today/Yesterday: 15 minutes (fresh data needed)
     * - Last 2-30 days: 60 minutes (1 hour)
     * - Last 31-90 days: 360 minutes (6 hours)
     * - Last 91+ days: 720 minutes (12 hours)
     */
    protected function getDynamicCacheTTL(Period $period): int
    {
        $daysDiff = $period->startDate->diffInDays($period->endDate);

        // Today/Yesterday (0-1 days) - 15 minutes
        if ($daysDiff < 2) {
            return 15;
        }

        // Last 2-30 days - 1 hour
        if ($daysDiff < 31) {
            return 60;
        }

        // Last 31-90 days - 6 hours
        if ($daysDiff < 91) {
            return 360;
        }

        // 91+ days - 12 hours
        return 720;
    }

    /**
     * Get aggregated analytics from all active properties with cache-first strategy.
     * CRITICAL: Always return cache immediately if available, NEVER wait for fresh data.
     * Uses dynamic cache TTL based on period length for optimal performance.
     */
    public function getAggregatedAnalytics(Period $period, ?array $propertyIds = null): array
    {
        // Create cache key for aggregate data using centralized generator
        $cacheKey = CacheKey::forAggregate($propertyIds, $period->startDate, $period->endDate);
        $cacheTimestampKey = CacheKey::timestamp($cacheKey);
        $lastSuccessKey = CacheKey::lastSuccess($cacheKey);

        // Get dynamic TTL based on period
        $cacheTTL = $this->getDynamicCacheTTL($period);

        \Log::info('Fetching aggregate analytics', [
            'cache_key' => $cacheKey,
            'last_success_key' => $lastSuccessKey,
            'cache_ttl_minutes' => $cacheTTL,
            'period_days' => $period->startDate->diffInDays($period->endDate),
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
            $isFresh = $cacheAge !== null && $cacheAge < $cacheTTL;

            // Calculate next update based on dynamic TTL
            $nextUpdate = $cacheTimestamp ? $cacheTimestamp->copy()->addMinutes($cacheTTL) : null;
            $nextUpdateIn = $nextUpdate ? max(0, now()->diffInMinutes($nextUpdate, false)) : 0;

            // Get last_synced_at from most recent property
            $lastSyncedAt = $this->getMostRecentSyncTime($propertyIds);

            // If cache is stale and not currently refreshing, dispatch background refresh
            if (! $isFresh && ! Cache::has(CacheKey::refreshing($cacheKey))) {
                \Log::info('Cache is stale, dispatching background refresh', [
                    'cache_age_minutes' => $cacheAge,
                    'cache_ttl_minutes' => $cacheTTL,
                ]);
                $this->dispatchAggregateBackgroundRefresh($period, $propertyIds, $cacheKey);
            }

            return array_merge($cachedData, [
                'cache_info' => [
                    'last_updated' => $cacheTimestamp ? $cacheTimestamp->toIso8601String() : ($lastSyncedAt ? $lastSyncedAt->toIso8601String() : null),
                    'cache_age_minutes' => $cacheAge, // Use actual cache age, not property sync time
                    'next_update_in_minutes' => abs($nextUpdateIn),
                    'cache_ttl_minutes' => $cacheTTL,
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

        // PRIORITY 3: NO CACHE AT ALL - Fetch synchronously from daily cache
        // With daily aggregation system, we don't need background job for initial load
        // Data should be available instantly from 365-day daily cache
        \Log::info('No aggregate cache found, fetching from daily cache');

        try {
            // Build query for properties
            $query = GaProperty::active()->with('project');
            if ($propertyIds) {
                $query->whereIn('property_id', $propertyIds);
            }

            $properties = $query->get();

            if ($properties->isEmpty()) {
                \Log::warning('No active properties found');

                return $this->getEmptyDataStructure($period, false, 'No active properties found');
            }

            // Fetch data from daily cache (should be instant!)
            $data = $this->aggregator->getDashboardData($properties, $period);

            // Store with dynamic TTL based on period
            Cache::put($cacheKey, $data, now()->addMinutes($cacheTTL));
            Cache::put(CacheKey::timestamp($cacheKey), now(), now()->addMinutes($cacheTTL));

            // Store as "last known good data" for fallback
            Cache::put(CacheKey::lastSuccess($cacheKey), $data, now()->addYears(10));

            \Log::info('Cached aggregate data', [
                'cache_ttl_minutes' => $cacheTTL,
                'properties_count' => $properties->count(),
            ]);

            return array_merge($data, [
                'cache_info' => [
                    'last_updated' => now()->toIso8601String(),
                    'cache_age_minutes' => 0,
                    'next_update_in_minutes' => $cacheTTL,
                    'cache_ttl_minutes' => $cacheTTL,
                    'is_fresh' => true,
                    'is_updating' => false,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch aggregate data from daily cache', [
                'error' => $e->getMessage(),
            ]);

            return $this->getEmptyDataStructure($period, false, $e->getMessage());
        }
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

        dispatch(function () use ($period, $propertyIds, $cacheKey, $aggregator, $days, $refreshingKey) {
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

                // Build query for properties
                $query = GaProperty::active()->with('project');
                if ($propertyIds) {
                    $query->whereIn('property_id', $propertyIds);
                }

                $totalCount = $query->count();

                if ($totalCount === 0) {
                    \Log::warning('No active properties found for background refresh');
                    $syncLog->markFailed('No active properties found');

                    return;
                }

                \Log::info("Fetching dashboard data for {$totalCount} properties");

                // For large datasets, process in chunks to prevent memory issues
                $chunkThreshold = config('analytics.chunking.chunk_threshold', 100);
                if ($totalCount > $chunkThreshold) {
                    $data = $this->aggregatePropertiesInChunks($query, $period, $totalCount);
                } else {
                    // For small datasets, fetch all at once
                    $properties = $query->get();
                    $data = $aggregator->getDashboardData($properties, $period);
                }

                // Store with 30-minute expiry
                Cache::put($cacheKey, $data, now()->addMinutes(30));
                Cache::put(CacheKey::timestamp($cacheKey), now(), now()->addMinutes(30));

                // Store as "last known good data" that never expires (for instant fallback)
                $lastSuccessKey = CacheKey::lastSuccess($cacheKey);
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
     * Aggregate properties in chunks to prevent memory issues.
     * Processes properties in configurable batches and merges results.
     */
    protected function aggregatePropertiesInChunks($query, Period $period, int $totalCount): array
    {
        $chunkSize = config('analytics.chunking.properties_per_chunk', 100);
        $aggregatedData = null;

        \Log::info("Processing {$totalCount} properties in chunks of {$chunkSize}");

        $query->chunk($chunkSize, function ($properties) use ($period, &$aggregatedData) {
            $chunkData = $this->aggregator->getDashboardData($properties, $period);

            if ($aggregatedData === null) {
                // First chunk - initialize aggregated data
                $aggregatedData = $chunkData;
            } else {
                // Merge subsequent chunks
                $aggregatedData = $this->mergeAggregatedData($aggregatedData, $chunkData);
            }
        });

        return $aggregatedData ?? $this->getEmptyDataStructure($period);
    }

    /**
     * Merge two sets of aggregated analytics data.
     */
    protected function mergeAggregatedData(array $data1, array $data2): array
    {
        // Merge totals
        $mergedTotals = [
            'activeUsers' => ($data1['totals']['activeUsers'] ?? 0) + ($data2['totals']['activeUsers'] ?? 0),
            'newUsers' => ($data1['totals']['newUsers'] ?? 0) + ($data2['totals']['newUsers'] ?? 0),
            'sessions' => ($data1['totals']['sessions'] ?? 0) + ($data2['totals']['sessions'] ?? 0),
            'screenPageViews' => ($data1['totals']['screenPageViews'] ?? 0) + ($data2['totals']['screenPageViews'] ?? 0),
        ];

        // Calculate weighted averages for rate metrics
        $totalProperties = ($data1['properties_count'] ?? 0) + ($data2['properties_count'] ?? 0);
        if ($totalProperties > 0) {
            $weight1 = ($data1['properties_count'] ?? 0) / $totalProperties;
            $weight2 = ($data2['properties_count'] ?? 0) / $totalProperties;

            $mergedTotals['bounceRate'] = (($data1['totals']['bounceRate'] ?? 0) * $weight1) +
                                         (($data2['totals']['bounceRate'] ?? 0) * $weight2);
            $mergedTotals['averageSessionDuration'] = (($data1['totals']['averageSessionDuration'] ?? 0) * $weight1) +
                                                      (($data2['totals']['averageSessionDuration'] ?? 0) * $weight2);
        } else {
            $mergedTotals['bounceRate'] = 0;
            $mergedTotals['averageSessionDuration'] = 0;
        }

        // Merge property breakdowns
        $mergedPropertyBreakdown = array_merge(
            $data1['property_breakdown'] ?? [],
            $data2['property_breakdown'] ?? []
        );

        // Merge and sort top pages
        $mergedTopPages = array_merge($data1['top_pages'] ?? [], $data2['top_pages'] ?? []);
        usort($mergedTopPages, fn ($a, $b) => $b['pageviews'] <=> $a['pageviews']);
        $mergedTopPages = array_slice($mergedTopPages, 0, 20); // Keep top 20

        // Merge traffic sources
        $mergedTrafficSources = $this->mergeTrafficSources(
            $data1['traffic_sources'] ?? [],
            $data2['traffic_sources'] ?? []
        );

        // Merge devices
        $mergedDevices = $this->mergeDevices(
            $data1['devices'] ?? [],
            $data2['devices'] ?? []
        );

        return [
            'totals' => $mergedTotals,
            'property_breakdown' => $mergedPropertyBreakdown,
            'successful_fetches' => ($data1['successful_fetches'] ?? 0) + ($data2['successful_fetches'] ?? 0),
            'top_pages' => $mergedTopPages,
            'traffic_sources' => $mergedTrafficSources,
            'devices' => $mergedDevices,
            'period' => $data1['period'] ?? $data2['period'],
            'properties_count' => $totalProperties,
            'errors' => array_merge($data1['errors'] ?? [], $data2['errors'] ?? []),
        ];
    }

    /**
     * Merge traffic sources from two datasets.
     */
    protected function mergeTrafficSources(array $sources1, array $sources2): array
    {
        $merged = [];

        foreach (array_merge($sources1, $sources2) as $source) {
            $key = $source['source'].'_'.$source['medium'];

            if (! isset($merged[$key])) {
                $merged[$key] = $source;
            } else {
                $merged[$key]['sessions'] += $source['sessions'];
                $merged[$key]['users'] += $source['users'];
            }
        }

        $result = array_values($merged);
        usort($result, fn ($a, $b) => $b['sessions'] <=> $a['sessions']);

        return $result;
    }

    /**
     * Merge device data from two datasets.
     */
    protected function mergeDevices(array $devices1, array $devices2): array
    {
        $merged = [];

        foreach (array_merge($devices1, $devices2) as $device) {
            $category = $device['device'];

            if (! isset($merged[$category])) {
                $merged[$category] = $device;
            } else {
                $merged[$category]['users'] += $device['users'];
                $merged[$category]['sessions'] += $device['sessions'];
            }
        }

        return array_values($merged);
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

    /**
     * Create period from named period.
     * Uses server timezone (Asia/Jakarta) for accurate date calculations.
     */
    public function createPeriodFromName(string $periodName): Period
    {
        return match ($periodName) {
            'today' => Period::today(),
            'yesterday' => Period::yesterday(),
            'this_week' => Period::thisWeek(),
            'last_week' => Period::lastWeek(),
            'this_month' => Period::thisMonth(),
            'last_month' => Period::lastMonth(),
            'this_year' => Period::thisYear(),
            default => Period::days(30), // Fallback
        };
    }

    /**
     * Get analytics comparison between current and previous period.
     * Calculates changes and trends for all metrics.
     */
    public function getComparisonAnalytics(Period $currentPeriod, ?array $propertyIds = null): array
    {
        // Calculate previous period with same duration
        $duration = $currentPeriod->startDate->diffInDays($currentPeriod->endDate);
        $previousStart = $currentPeriod->startDate->copy()->subDays($duration + 1);
        $previousEnd = $currentPeriod->startDate->copy()->subDay();
        $previousPeriod = Period::create($previousStart, $previousEnd);

        // Fetch both periods
        $current = $this->getAggregatedAnalytics($currentPeriod, $propertyIds);
        $previous = $this->getAggregatedAnalytics($previousPeriod, $propertyIds);

        // Calculate changes for totals
        $comparison = [
            'current' => $current,
            'previous' => $previous,
            'changes' => $this->calculateChanges($current['totals'], $previous['totals']),
            'periods' => [
                'current' => [
                    'start_date' => $currentPeriod->startDate->format('Y-m-d'),
                    'end_date' => $currentPeriod->endDate->format('Y-m-d'),
                    'days' => $duration + 1,
                ],
                'previous' => [
                    'start_date' => $previousPeriod->startDate->format('Y-m-d'),
                    'end_date' => $previousPeriod->endDate->format('Y-m-d'),
                    'days' => $duration + 1,
                ],
            ],
        ];

        return $comparison;
    }

    /**
     * Calculate metric changes between two periods.
     */
    protected function calculateChanges(array $current, array $previous): array
    {
        $changes = [];

        foreach ($current as $metric => $currentValue) {
            $previousValue = $previous[$metric] ?? 0;

            $changes[$metric] = [
                'current' => $currentValue,
                'previous' => $previousValue,
                'absolute_change' => $currentValue - $previousValue,
                'percentage_change' => $this->calculatePercentageChange($currentValue, $previousValue),
                'trend' => $this->determineTrend($currentValue, $previousValue),
            ];
        }

        return $changes;
    }

    /**
     * Calculate percentage change between two values.
     */
    protected function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Determine trend direction.
     */
    protected function determineTrend(float $current, float $previous): string
    {
        $change = $current - $previous;

        if ($change > 0) {
            return 'up';
        }

        if ($change < 0) {
            return 'down';
        }

        return 'neutral';
    }

    /**
     * Get property analytics with comparison.
     */
    public function getPropertyAnalyticsWithComparison(GaProperty $property, Period $currentPeriod): array
    {
        // Calculate previous period with same duration
        $duration = $currentPeriod->startDate->diffInDays($currentPeriod->endDate);
        $previousStart = $currentPeriod->startDate->copy()->subDays($duration + 1);
        $previousEnd = $currentPeriod->startDate->copy()->subDay();
        $previousPeriod = Period::create($previousStart, $previousEnd);

        // Fetch both periods
        $current = $this->getPropertyAnalytics($property, $currentPeriod);
        $previous = $this->getPropertyAnalytics($property, $previousPeriod);

        return [
            'current' => $current,
            'previous' => $previous,
            'changes' => $this->calculateChanges($current['metrics'], $previous['metrics']),
            'periods' => [
                'current' => [
                    'start_date' => $currentPeriod->startDate->format('Y-m-d'),
                    'end_date' => $currentPeriod->endDate->format('Y-m-d'),
                    'days' => $duration + 1,
                ],
                'previous' => [
                    'start_date' => $previousPeriod->startDate->format('Y-m-d'),
                    'end_date' => $previousPeriod->endDate->format('Y-m-d'),
                    'days' => $duration + 1,
                ],
            ],
        ];
    }

    /**
     * Get metrics dashboard data.
     */
    public function getMetricsDashboard(): array
    {
        return $this->metrics->getDashboardMetrics();
    }

    /**
     * Get realtime active users across all active properties.
     * Returns total active users in the last 30 minutes.
     * Cached for 30 seconds to reduce API quota usage.
     */
    public function getRealtimeActiveUsers(?array $propertyIds = null): array
    {
        // Generate cache key based on property IDs
        $cacheKey = 'realtime_users:'.($propertyIds ? implode(',', $propertyIds) : 'all');
        $lastSuccessKey = $cacheKey.':last_success';
        $refreshingKey = $cacheKey.':refreshing';

        // Check if cached data exists
        $cachedData = Cache::get($cacheKey);
        $cacheTimestamp = Cache::get($cacheKey.':timestamp');

        // PRIORITY 1: Return fresh cached data if available (< 30 seconds old)
        if ($cachedData && $cacheTimestamp) {
            $cacheAge = now()->diffInSeconds($cacheTimestamp);
            if ($cacheAge < 30) {
                return $cachedData;
            }

            // Cache is stale but exists - return it while refreshing in background
            if (! Cache::has($refreshingKey)) {
                Cache::put($refreshingKey, true, now()->addSeconds(30));

                // Dispatch background refresh
                dispatch(function () use ($propertyIds, $cacheKey, $lastSuccessKey, $refreshingKey) {
                    try {
                        $query = GaProperty::active();

                        if ($propertyIds) {
                            $query->whereIn('property_id', $propertyIds);
                        }

                        $properties = $query->get();
                        $totalActiveUsers = 0;
                        $propertyBreakdown = [];

                        foreach ($properties as $property) {
                            $activeUsers = app(\App\Services\GoogleAnalytics\AnalyticsDataFetcher::class)
                                ->fetchRealtimeUsers($property);

                            $totalActiveUsers += $activeUsers;

                            if ($activeUsers > 0) {
                                $propertyBreakdown[] = [
                                    'property_id' => $property->property_id,
                                    'property_name' => $property->name,
                                    'active_users' => $activeUsers,
                                ];
                            }
                        }

                        usort($propertyBreakdown, fn ($a, $b) => $b['active_users'] <=> $a['active_users']);

                        $result = [
                            'total_active_users' => $totalActiveUsers,
                            'property_breakdown' => $propertyBreakdown,
                            'properties_count' => $properties->count(),
                            'timestamp' => now()->toIso8601String(),
                        ];

                        Cache::put($cacheKey, $result, now()->addSeconds(30));
                        Cache::put($cacheKey.':timestamp', now(), now()->addSeconds(30));
                        Cache::put($lastSuccessKey, $result, now()->addYears(10));
                    } finally {
                        Cache::forget($refreshingKey);
                    }
                })->afterResponse();
            }

            return $cachedData;
        }

        // PRIORITY 2: Return last known good data if exists (for instant display)
        $lastSuccess = Cache::get($lastSuccessKey);
        if ($lastSuccess) {
            // Dispatch background refresh to get new data
            if (! Cache::has($refreshingKey)) {
                Cache::put($refreshingKey, true, now()->addSeconds(30));

                dispatch(function () use ($propertyIds, $cacheKey, $lastSuccessKey, $refreshingKey) {
                    try {
                        $query = GaProperty::active();

                        if ($propertyIds) {
                            $query->whereIn('property_id', $propertyIds);
                        }

                        $properties = $query->get();
                        $totalActiveUsers = 0;
                        $propertyBreakdown = [];

                        foreach ($properties as $property) {
                            $activeUsers = app(\App\Services\GoogleAnalytics\AnalyticsDataFetcher::class)
                                ->fetchRealtimeUsers($property);

                            $totalActiveUsers += $activeUsers;

                            if ($activeUsers > 0) {
                                $propertyBreakdown[] = [
                                    'property_id' => $property->property_id,
                                    'property_name' => $property->name,
                                    'active_users' => $activeUsers,
                                ];
                            }
                        }

                        usort($propertyBreakdown, fn ($a, $b) => $b['active_users'] <=> $a['active_users']);

                        $result = [
                            'total_active_users' => $totalActiveUsers,
                            'property_breakdown' => $propertyBreakdown,
                            'properties_count' => $properties->count(),
                            'timestamp' => now()->toIso8601String(),
                        ];

                        Cache::put($cacheKey, $result, now()->addSeconds(30));
                        Cache::put($cacheKey.':timestamp', now(), now()->addSeconds(30));
                        Cache::put($lastSuccessKey, $result, now()->addYears(10));
                    } finally {
                        Cache::forget($refreshingKey);
                    }
                })->afterResponse();
            }

            return $lastSuccess;
        }

        // PRIORITY 3: No cache at all - fetch synchronously
        $query = GaProperty::active();

        if ($propertyIds) {
            $query->whereIn('property_id', $propertyIds);
        }

        $properties = $query->get();

        $totalActiveUsers = 0;
        $propertyBreakdown = [];

        foreach ($properties as $property) {
            $activeUsers = $this->dataFetcher->fetchRealtimeUsers($property);

            $totalActiveUsers += $activeUsers;

            if ($activeUsers > 0) {
                $propertyBreakdown[] = [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'active_users' => $activeUsers,
                ];
            }
        }

        // Sort by active users descending
        usort($propertyBreakdown, fn ($a, $b) => $b['active_users'] <=> $a['active_users']);

        $result = [
            'total_active_users' => $totalActiveUsers,
            'property_breakdown' => $propertyBreakdown,
            'properties_count' => $properties->count(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Cache the result for 30 seconds
        Cache::put($cacheKey, $result, now()->addSeconds(30));
        Cache::put($cacheKey.':timestamp', now(), now()->addSeconds(30));
        // Store as "last known good data" that never expires
        Cache::put($lastSuccessKey, $result, now()->addYears(10));

        return $result;
    }
}
