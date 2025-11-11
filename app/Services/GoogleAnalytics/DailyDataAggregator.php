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
     */
    public function getDataForPeriod(GaProperty $property, Period $period): array
    {
        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available, return empty
        if (empty($dailyData['rows'])) {
            return [
                'totals' => [],
                'rows' => [],
                'is_fresh' => false,
            ];
        }

        // Filter rows by requested period
        $filteredRows = $this->filterRowsByPeriod($dailyData['rows'], $period);

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
        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available, return empty
        if (empty($dailyData['top_pages'])) {
            return [];
        }

        // Filter and aggregate top pages by period
        return $this->filterTopPagesByPeriod($dailyData['top_pages'], $period, $limit);
    }

    /**
     * Get traffic sources for a specific period from cached 365-day data.
     */
    public function getTrafficSourcesForPeriod(GaProperty $property, Period $period): array
    {
        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available, return empty
        if (empty($dailyData['traffic_sources'])) {
            return [];
        }

        // Filter and aggregate traffic sources by period
        return $this->filterTrafficSourcesByPeriod($dailyData['traffic_sources'], $period);
    }

    /**
     * Get devices for a specific period from cached 365-day data.
     */
    public function getDevicesForPeriod(GaProperty $property, Period $period): array
    {
        // Get cached 365-day data
        $dailyData = $this->getDailyData($property);

        // If no daily data available, return empty
        if (empty($dailyData['devices'])) {
            return [];
        }

        // Filter and aggregate devices by period
        return $this->filterDevicesByPeriod($dailyData['devices'], $period);
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

                // Capture dataFetcher dependency for use in closure
                $dataFetcher = $this->dataFetcher;
                $ttl = self::DAILY_CACHE_TTL;

                dispatch(function () use ($property, $cacheKey, $timestampKey, $refreshingKey, $dataFetcher, $ttl) {
                    try {
                        // Fetch 365 days of data with all data types
                        $period = Period::days(365);

                        // Fetch metrics
                        $metricsResult = $dataFetcher->fetchMetrics($property, $period);
                        $metricsData = isset($metricsResult['data']) ? $metricsResult['data'] : $metricsResult;

                        // Fetch top pages with date dimension
                        $topPages = $dataFetcher->fetchTopPagesDaily($property, $period, 100);

                        // Fetch traffic sources with date dimension
                        $trafficSources = $dataFetcher->fetchTrafficSourcesDaily($property, $period);

                        // Fetch devices with date dimension
                        $devices = $dataFetcher->fetchDevicesDaily($property, $period);

                        $freshData = [
                            'rows' => $metricsData['rows'] ?? [],
                            'totals' => $metricsData['totals'] ?? [],
                            'top_pages' => $topPages,
                            'traffic_sources' => $trafficSources,
                            'devices' => $devices,
                        ];

                        Cache::put($cacheKey, $freshData, now()->addMinutes($ttl));
                        Cache::put($timestampKey, now(), now()->addMinutes($ttl));
                    } catch (\Exception $e) {
                        \Log::error('Failed to refresh daily data in background', [
                            'property_id' => $property->property_id,
                            'error' => $e->getMessage(),
                        ]);
                    } finally {
                        Cache::forget($refreshingKey);
                    }
                })->afterResponse();
            }

            return [
                ...$cached,
                'is_fresh' => false,
                'cached_at' => $timestamp->toIso8601String(),
            ];
        }

        // No cache - fetch synchronously
        try {
            $freshData = $this->fetchDailyDataFromGA($property);

            Cache::put($cacheKey, $freshData, now()->addMinutes(self::DAILY_CACHE_TTL));
            Cache::put($timestampKey, now(), now()->addMinutes(self::DAILY_CACHE_TTL));

            return [
                ...$freshData,
                'is_fresh' => true,
                'cached_at' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to fetch daily data from GA', [
                'property_id' => $property->property_id,
                'error' => $e->getMessage(),
            ]);

            // Return empty if fetch fails
            return [
                'totals' => [],
                'rows' => [],
                'is_fresh' => false,
            ];
        }
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

        return array_values(array_filter($rows, function ($row) use ($startDate, $endDate) {
            $rowDate = $row['date'];

            return $rowDate >= $startDate && $rowDate <= $endDate;
        }));
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

        // Aggregate by source and medium
        $sourcesByKey = [];
        foreach ($filteredSources as $source) {
            $key = $source['source'].'_'.$source['medium'];

            if (! isset($sourcesByKey[$key])) {
                $sourcesByKey[$key] = [
                    'source' => $source['source'],
                    'medium' => $source['medium'],
                    'sessions' => 0,
                    'users' => 0,
                ];
            }

            $sourcesByKey[$key]['sessions'] += $source['sessions'];
            $sourcesByKey[$key]['users'] += $source['users'];
        }

        // Sort by sessions
        $aggregated = array_values($sourcesByKey);
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
