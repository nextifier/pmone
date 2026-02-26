<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\Concerns\CalculatesTotalsFromRows;
use Illuminate\Support\Facades\Cache;

/**
 * Daily Data Aggregator Service.
 *
 * Fetches 365 days of per-day data from Google Analytics once,
 * caches it, then aggregates based on requested period.
 *
 * Benefits:
 * - Single fetch instead of multiple period requests
 * - Instant period switching (no GA API calls)
 * - Supports custom date ranges without refetching
 * - Reduced GA API quota usage
 */
class DailyDataAggregator
{
    use CalculatesTotalsFromRows;

    /**
     * Cache duration for daily data (24 hours)
     */
    protected const DAILY_CACHE_TTL = 60 * 24;

    public function __construct(
        protected AnalyticsDataFetcher $dataFetcher
    ) {}

    /**
     * Get aggregated data for a specific period.
     * Uses cached 365-day data and aggregates on-demand.
     *
     * Special handling for "today" period:
     * - Google Analytics API typically has 24-48 hour processing delay
     * - Data for current day may not be available in standard reports
     * - Falls back to direct API fetch if today's data not in cache
     */
    public function getDataForPeriod(GaProperty $property, Period $period): array
    {
        // Check if this is "today" period
        $isToday = $period->startDate->format('Y-m-d') === now()->format('Y-m-d')
                   && $period->endDate->format('Y-m-d') === now()->format('Y-m-d');

        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available, try direct fetch for today
        if (empty($dailyData['rows'])) {
            if ($isToday) {
                \Log::info('No 365-day cache data, attempting direct fetch for today', [
                    'property_id' => $property->property_id,
                ]);

                return $this->fetchTodayDataDirectly($property, $period);
            }

            return [
                'totals' => [],
                'rows' => [],
                'is_fresh' => false,
            ];
        }

        // Filter rows by requested period
        $filteredRows = $this->filterRowsByPeriod($dailyData['rows'], $period);

        // Special case: If filtering for "today" returns empty, try direct fetch
        if ($isToday && empty($filteredRows)) {
            \Log::info('Today data not found in 365-day cache, fetching directly from GA API', [
                'property_id' => $property->property_id,
                'cache_has_rows' => count($dailyData['rows']),
            ]);

            return $this->fetchTodayDataDirectly($property, $period);
        }

        // Calculate totals from filtered rows
        $totals = $this->calculateTotalsFromRows($filteredRows);

        return [
            'totals' => $totals,
            'rows' => $filteredRows,
            'is_fresh' => $dailyData['is_fresh'] ?? false,
            'cached_at' => $dailyData['cached_at'] ?? null,
        ];
    }

    /**
     * Get top pages for a specific period from cached 365-day data.
     */
    public function getTopPagesForPeriod(GaProperty $property, Period $period, int $limit = 20): array
    {
        // Check if this is "today" period
        $isToday = $period->startDate->format('Y-m-d') === now()->format('Y-m-d')
                   && $period->endDate->format('Y-m-d') === now()->format('Y-m-d');

        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available and this is today, try direct fetch
        if (empty($dailyData['top_pages'])) {
            if ($isToday) {
                try {
                    return $this->dataFetcher->fetchTopPages($property, $period, $limit);
                } catch (\Exception $e) {
                    \Log::error('Failed to fetch today top pages', [
                        'property_id' => $property->property_id,
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            }

            return [];
        }

        $filtered = $this->filterTopPagesByPeriod($dailyData['top_pages'], $period, $limit);

        // If filtering for today returns empty, try direct fetch
        if ($isToday && empty($filtered)) {
            try {
                return $this->dataFetcher->fetchTopPages($property, $period, $limit);
            } catch (\Exception $e) {
                \Log::error('Failed to fetch today top pages', [
                    'property_id' => $property->property_id,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        }

        // Filter and aggregate top pages by period
        return $filtered;
    }

    /**
     * Get traffic sources for a specific period from cached 365-day data.
     */
    public function getTrafficSourcesForPeriod(GaProperty $property, Period $period): array
    {
        // Check if this is "today" period
        $isToday = $period->startDate->format('Y-m-d') === now()->format('Y-m-d')
                   && $period->endDate->format('Y-m-d') === now()->format('Y-m-d');

        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available and this is today, try direct fetch
        if (empty($dailyData['traffic_sources'])) {
            if ($isToday) {
                try {
                    return $this->dataFetcher->fetchTrafficSources($property, $period);
                } catch (\Exception $e) {
                    \Log::error('Failed to fetch today traffic sources', [
                        'property_id' => $property->property_id,
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            }

            return [];
        }

        $filtered = $this->filterTrafficSourcesByPeriod($dailyData['traffic_sources'], $period);

        // If filtering for today returns empty, try direct fetch
        if ($isToday && empty($filtered)) {
            try {
                return $this->dataFetcher->fetchTrafficSources($property, $period);
            } catch (\Exception $e) {
                \Log::error('Failed to fetch today traffic sources', [
                    'property_id' => $property->property_id,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        }

        // Filter and aggregate traffic sources by period
        return $filtered;
    }

    /**
     * Get devices for a specific period from cached 365-day data.
     */
    public function getDevicesForPeriod(GaProperty $property, Period $period): array
    {
        // Check if this is "today" period
        $isToday = $period->startDate->format('Y-m-d') === now()->format('Y-m-d')
                   && $period->endDate->format('Y-m-d') === now()->format('Y-m-d');

        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available and this is today, try direct fetch
        if (empty($dailyData['devices'])) {
            if ($isToday) {
                try {
                    return $this->dataFetcher->fetchDevices($property, $period);
                } catch (\Exception $e) {
                    \Log::error('Failed to fetch today devices', [
                        'property_id' => $property->property_id,
                        'error' => $e->getMessage(),
                    ]);

                    return [];
                }
            }

            return [];
        }

        $filtered = $this->filterDevicesByPeriod($dailyData['devices'], $period);

        // If filtering for today returns empty, try direct fetch
        if ($isToday && empty($filtered)) {
            try {
                return $this->dataFetcher->fetchDevices($property, $period);
            } catch (\Exception $e) {
                \Log::error('Failed to fetch today devices', [
                    'property_id' => $property->property_id,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        }

        // Filter and aggregate devices by period
        return $filtered;
    }

    /**
     * Fetch today's data directly from Google Analytics API.
     * Used when today's data is not available in 365-day cache.
     *
     * Note: GA API may have processing delays, so today's data might be incomplete or unavailable.
     * This method attempts to fetch what's available, but may return empty/zero metrics.
     */
    protected function fetchTodayDataDirectly(GaProperty $property, Period $period): array
    {
        try {
            // Attempt to fetch today's metrics directly
            $metricsResult = $this->dataFetcher->fetchMetrics($property, $period);
            $metricsData = isset($metricsResult['data']) ? $metricsResult['data'] : $metricsResult;

            // If we got data, return it
            if (! empty($metricsData['rows']) || ! empty($metricsData['totals'])) {
                \Log::info('Successfully fetched today data directly from GA API', [
                    'property_id' => $property->property_id,
                    'has_rows' => ! empty($metricsData['rows']),
                    'has_totals' => ! empty($metricsData['totals']),
                ]);

                // Calculate totals from rows if totals are empty
                $totals = $metricsData['totals'] ?? [];
                if (empty($totals) && ! empty($metricsData['rows'])) {
                    $totals = $this->calculateTotalsFromRows($metricsData['rows']);
                }

                return [
                    'totals' => $totals,
                    'rows' => $metricsData['rows'] ?? [],
                    'is_fresh' => true,
                    'cached_at' => now()->toIso8601String(),
                ];
            }

            \Log::warning('GA API returned empty data for today', [
                'property_id' => $property->property_id,
                'reason' => 'GA typically has 24-48h processing delay, today data may not be available yet',
            ]);

            return [
                'totals' => [],
                'rows' => [],
                'is_fresh' => false,
                'cached_at' => now()->toIso8601String(),
                'note' => 'Data for today is not yet available from Google Analytics API (24-48h processing delay)',
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to fetch today data directly from GA API', [
                'property_id' => $property->property_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'totals' => [],
                'rows' => [],
                'is_fresh' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get 365 days of daily data from cache or fetch from GA.
     */
    protected function getDailyData(GaProperty $property): array
    {
        $cacheKey = $this->getDailyCacheKey($property->property_id);
        $timestampKey = $cacheKey.':timestamp';
        $refreshingKey = $cacheKey.':refreshing';

        // Check if we have cached data
        $cached = Cache::get($cacheKey);
        $timestamp = Cache::get($timestampKey);

        // Return fresh cache (less than 24 hours old)
        if ($cached && $timestamp) {
            $ageMinutes = now()->diffInMinutes($timestamp);

            if ($ageMinutes < self::DAILY_CACHE_TTL) {
                return [
                    ...$cached,
                    'is_fresh' => true,
                    'cached_at' => $timestamp->toIso8601String(),
                ];
            }

            // Stale cache - return it but refresh in background
            if (! Cache::has($refreshingKey)) {
                Cache::put($refreshingKey, true, now()->addMinutes(30));

                // Dispatch job instead of closure to prevent memory leaks in Octane
                dispatch(new \App\Jobs\RefreshDailyCache(
                    propertyId: $property->id,
                    cacheKey: $cacheKey,
                    timestampKey: $timestampKey,
                    refreshingKey: $refreshingKey,
                    ttl: self::DAILY_CACHE_TTL
                ));
            }

            return [
                ...$cached,
                'is_fresh' => false,
                'cached_at' => $timestamp->toIso8601String(),
            ];
        }

        // No cache - dispatch background job and return empty
        // NEVER fetch synchronously to avoid blocking PHP-FPM workers
        if (! Cache::has($refreshingKey)) {
            Cache::put($refreshingKey, true, now()->addMinutes(30));

            \Log::info('No daily cache, dispatching RefreshDailyCache job', [
                'property_id' => $property->property_id,
            ]);

            dispatch(new \App\Jobs\RefreshDailyCache(
                propertyId: $property->id,
                cacheKey: $cacheKey,
                timestampKey: $timestampKey,
                refreshingKey: $refreshingKey,
                ttl: self::DAILY_CACHE_TTL
            ));
        }

        return [
            'totals' => [],
            'rows' => [],
            'is_fresh' => false,
            'is_loading' => true,
        ];
    }

    /**
     * Fetch 365 days of per-day data from Google Analytics.
     * Includes metrics, top pages, traffic sources, and devices.
     */
    protected function fetchDailyDataFromGA(GaProperty $property): array
    {
        // Create 365-day period
        $period = Period::days(365);

        // Fetch all data types in parallel
        try {
            // Fetch metrics
            $metricsResult = $this->dataFetcher->fetchMetrics($property, $period);
            $metricsData = isset($metricsResult['data']) ? $metricsResult['data'] : $metricsResult;

            // Fetch top pages with date dimension
            $topPages = $this->dataFetcher->fetchTopPagesDaily($property, $period, 100);

            // Fetch traffic sources with date dimension
            $trafficSources = $this->dataFetcher->fetchTrafficSourcesDaily($property, $period);

            // Fetch devices with date dimension
            $devices = $this->dataFetcher->fetchDevicesDaily($property, $period);

            return [
                'rows' => $metricsData['rows'] ?? [],
                'totals' => $metricsData['totals'] ?? [],
                'top_pages' => $topPages,
                'traffic_sources' => $trafficSources,
                'devices' => $devices,
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to fetch daily data from GA', [
                'property_id' => $property->property_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Filter rows by period date range.
     */
    protected function filterRowsByPeriod(array $rows, Period $period): array
    {
        $startDate = $period->startDate->format('Y-m-d');
        $endDate = $period->endDate->format('Y-m-d');

        // Debug logging for today period
        if ($startDate === $endDate && $startDate === now()->format('Y-m-d')) {
            $availableDates = array_map(fn ($row) => $row['date'] ?? 'no-date', $rows);
            $lastFiveDates = array_slice($availableDates, -5);

            \Log::info('Filtering rows for TODAY period', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_rows_in_cache' => count($rows),
                'last_5_dates_in_cache' => $lastFiveDates,
                'looking_for_date' => $startDate,
            ]);
        }

        $filtered = array_values(array_filter($rows, function ($row) use ($startDate, $endDate) {
            $rowDate = $row['date'];

            return $rowDate >= $startDate && $rowDate <= $endDate;
        }));

        // Debug logging for today period result
        if ($startDate === $endDate && $startDate === now()->format('Y-m-d')) {
            \Log::info('Filter result for TODAY period', [
                'filtered_rows_count' => count($filtered),
                'filtered_dates' => array_map(fn ($row) => $row['date'] ?? 'no-date', $filtered),
            ]);
        }

        return $filtered;
    }

    /**
     * Get cache key for daily data.
     */
    protected function getDailyCacheKey(string $propertyId): string
    {
        return "analytics:daily:{$propertyId}";
    }

    /**
     * Clear daily cache for a property.
     */
    public function clearDailyCache(string $propertyId): void
    {
        $cacheKey = $this->getDailyCacheKey($propertyId);
        Cache::forget($cacheKey);
        Cache::forget($cacheKey.':timestamp');
        Cache::forget($cacheKey.':refreshing');
    }

    /**
     * Check if daily data exists in cache for a property.
     */
    public function hasDailyCache(string $propertyId): bool
    {
        return Cache::has($this->getDailyCacheKey($propertyId));
    }

    /**
     * Filter and aggregate top pages by period date range.
     */
    protected function filterTopPagesByPeriod(array $pages, Period $period, int $limit = 20): array
    {
        $startDate = $period->startDate->format('Y-m-d');
        $endDate = $period->endDate->format('Y-m-d');

        // Filter pages by date range
        $filteredPages = array_filter($pages, function ($page) use ($startDate, $endDate) {
            $pageDate = $page['date'];

            return $pageDate >= $startDate && $pageDate <= $endDate;
        });

        // Aggregate pageviews by path
        $pagesByPath = [];
        foreach ($filteredPages as $page) {
            $key = $page['path'];

            if (! isset($pagesByPath[$key])) {
                $pagesByPath[$key] = [
                    'title' => $page['title'],
                    'path' => $page['path'],
                    'pageviews' => 0,
                ];
            }

            $pagesByPath[$key]['pageviews'] += $page['pageviews'];
        }

        // Sort by pageviews and take top N
        $aggregated = array_values($pagesByPath);
        usort($aggregated, fn ($a, $b) => $b['pageviews'] <=> $a['pageviews']);

        return array_slice($aggregated, 0, $limit);
    }

    /**
     * Filter and aggregate traffic sources by period date range.
     */
    protected function filterTrafficSourcesByPeriod(array $sources, Period $period): array
    {
        $startDate = $period->startDate->format('Y-m-d');
        $endDate = $period->endDate->format('Y-m-d');

        // Filter sources by date range
        $filteredSources = array_filter($sources, function ($source) use ($startDate, $endDate) {
            $sourceDate = $source['date'];

            return $sourceDate >= $startDate && $sourceDate <= $endDate;
        });

        // Aggregate by source, medium, campaign, and landing page
        // Bounce rate and avg duration use weighted average based on sessions
        $sourcesByKey = [];
        foreach ($filteredSources as $source) {
            $key = $source['source'].'_'.$source['medium'].'_'.($source['campaign'] ?? '(not set)').'_'.($source['landing_page'] ?? '(not set)');
            $sessions = $source['sessions'];

            if (! isset($sourcesByKey[$key])) {
                $sourcesByKey[$key] = [
                    'source' => $source['source'],
                    'medium' => $source['medium'],
                    'campaign' => $source['campaign'] ?? '(not set)',
                    'landing_page' => $source['landing_page'] ?? '(not set)',
                    'sessions' => 0,
                    'users' => 0,
                    'bounce_rate_weighted' => 0,
                    'avg_duration_weighted' => 0,
                ];
            }

            $sourcesByKey[$key]['sessions'] += $sessions;
            $sourcesByKey[$key]['users'] += $source['users'];
            $sourcesByKey[$key]['bounce_rate_weighted'] += ($source['bounce_rate'] ?? 0) * $sessions;
            $sourcesByKey[$key]['avg_duration_weighted'] += ($source['avg_duration'] ?? 0) * $sessions;
        }

        // Calculate final weighted averages
        $aggregated = array_values(array_map(function ($item) {
            $totalSessions = $item['sessions'];

            return [
                'source' => $item['source'],
                'medium' => $item['medium'],
                'campaign' => $item['campaign'],
                'landing_page' => $item['landing_page'],
                'sessions' => $item['sessions'],
                'users' => $item['users'],
                'bounce_rate' => $totalSessions > 0 ? round($item['bounce_rate_weighted'] / $totalSessions, 4) : 0,
                'avg_duration' => $totalSessions > 0 ? round($item['avg_duration_weighted'] / $totalSessions, 1) : 0,
            ];
        }, $sourcesByKey));

        // Sort by sessions
        usort($aggregated, fn ($a, $b) => $b['sessions'] <=> $a['sessions']);

        return $aggregated;
    }

    /**
     * Filter and aggregate devices by period date range.
     */
    protected function filterDevicesByPeriod(array $devices, Period $period): array
    {
        $startDate = $period->startDate->format('Y-m-d');
        $endDate = $period->endDate->format('Y-m-d');

        // Filter devices by date range
        $filteredDevices = array_filter($devices, function ($device) use ($startDate, $endDate) {
            $deviceDate = $device['date'];

            return $deviceDate >= $startDate && $deviceDate <= $endDate;
        });

        // Aggregate by device category
        $devicesByCategory = [];
        foreach ($filteredDevices as $device) {
            $category = $device['device'];

            if (! isset($devicesByCategory[$category])) {
                $devicesByCategory[$category] = [
                    'device' => $category,
                    'users' => 0,
                    'sessions' => 0,
                ];
            }

            $devicesByCategory[$category]['users'] += $device['users'];
            $devicesByCategory[$category]['sessions'] += $device['sessions'];
        }

        return array_values($devicesByCategory);
    }
}
