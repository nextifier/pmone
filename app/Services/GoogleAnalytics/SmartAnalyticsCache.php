<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsCacheKeyGenerator as CacheKey;
use App\Services\GoogleAnalytics\Concerns\CalculatesTotalsFromRows;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SmartAnalyticsCache
{
    use CalculatesTotalsFromRows;

    protected int $minCacheDuration;

    protected int $maxCacheDuration;

    public function __construct()
    {
        $this->minCacheDuration = config('analytics.smart_cache.min_duration', 5);
        $this->maxCacheDuration = config('analytics.smart_cache.max_duration', 30);
    }

    /**
     * Get data with smart caching strategy.
     * CACHE-FIRST STRATEGY: Always return cache if available, fetch in background if stale.
     */
    public function getDataWithSmartCache(
        GaProperty $property,
        string $startDate,
        string $endDate,
        callable $fetchCallback
    ): array {
        $cacheKey = $this->generateCacheKey($property->property_id, $startDate, $endDate);
        $rateLimitKey = $this->generateRateLimitKey($property->property_id);

        // Check if exact match exists in cache
        $cachedData = Cache::get($cacheKey);
        $cacheTimestamp = Cache::get(CacheKey::timestamp($cacheKey));

        // CACHE-FIRST: If cache exists, return it immediately (even if stale)
        if ($cachedData) {
            $isFresh = $this->isCacheStillFresh($cacheTimestamp);
            $lastUpdated = $cacheTimestamp ? $cacheTimestamp->toIso8601String() : null;
            $cacheAge = $cacheTimestamp ? now()->diffInMinutes($cacheTimestamp) : null;

            // Calculate next update time based on sync frequency
            $nextUpdate = $cacheTimestamp ? $cacheTimestamp->copy()->addMinutes($property->sync_frequency) : null;
            $nextUpdateIn = $nextUpdate ? max(0, now()->diffInMinutes($nextUpdate, false)) : 0;

            // If cache is stale, dispatch background job to refresh it
            if (! $isFresh && ! $this->isRefreshJobQueued($cacheKey)) {
                $this->dispatchBackgroundRefresh($property, $startDate, $endDate, $fetchCallback, $cacheKey);
            }

            return [
                'data' => $cachedData,
                'cached_at' => $cacheTimestamp,
                'last_updated' => $lastUpdated,
                'cache_age_minutes' => $cacheAge,
                'next_update_in_minutes' => abs($nextUpdateIn),
                'is_fresh' => $isFresh,
                'is_updating' => ! $isFresh,
            ];
        }

        // NEW: Try to find subset from larger cached period
        $subsetData = $this->findSubsetFromLargerCache($property->property_id, $startDate, $endDate);

        if ($subsetData) {
            return [
                'data' => $subsetData,
                'cached_at' => now(),
                'last_updated' => now()->toIso8601String(),
                'cache_age_minutes' => 0,
                'next_update_in_minutes' => $property->sync_frequency,
                'is_fresh' => true,
                'from_subset' => true,
            ];
        }

        // Rate limiting: prevent too many requests
        $maxAttempts = $this->getMaxAttemptsPerProperty($property);
        $decayMinutes = $property->sync_frequency;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $availableIn = RateLimiter::availableIn($rateLimitKey);
            throw new \Exception("Rate limit exceeded. Available in {$availableIn} seconds.");
        }

        // Fetch new data (only if no cache exists)
        RateLimiter::hit($rateLimitKey, $decayMinutes * 60); // Convert to seconds

        $freshData = $fetchCallback();

        // Dynamic cache duration
        $cacheDuration = $this->getDynamicCacheDuration($property);

        Cache::put($cacheKey, $freshData, now()->addMinutes($cacheDuration));
        Cache::put(CacheKey::timestamp($cacheKey), now(), now()->addMinutes($cacheDuration));

        return [
            'data' => $freshData,
            'cached_at' => now(),
            'last_updated' => now()->toIso8601String(),
            'cache_age_minutes' => 0,
            'next_update_in_minutes' => $property->sync_frequency,
            'is_fresh' => true,
        ];
    }

    /**
     * Check if a background refresh job is already queued for this cache key.
     */
    protected function isRefreshJobQueued(string $cacheKey): bool
    {
        return Cache::has(CacheKey::refreshing($cacheKey));
    }

    /**
     * Dispatch background job to refresh cache data.
     */
    protected function dispatchBackgroundRefresh(
        GaProperty $property,
        string $startDate,
        string $endDate,
        callable $fetchCallback,
        string $cacheKey
    ): void {
        // Mark as refreshing to prevent duplicate jobs
        Cache::put(CacheKey::refreshing($cacheKey), true, now()->addMinutes(5));

        // Dispatch job to fetch new data in background
        dispatch(function () use ($property, $fetchCallback, $cacheKey) {
            try {
                $freshData = $fetchCallback();
                $cacheDuration = $this->getDynamicCacheDuration($property);

                Cache::put($cacheKey, $freshData, now()->addMinutes($cacheDuration));
                Cache::put(CacheKey::timestamp($cacheKey), now(), now()->addMinutes($cacheDuration));
            } catch (\Exception $e) {
                \Log::error("Background cache refresh failed for {$cacheKey}: {$e->getMessage()}");
            } finally {
                Cache::forget(CacheKey::refreshing($cacheKey));
            }
        })->afterResponse();
    }

    /**
     * Generate cache key for analytics data using centralized generator.
     */
    protected function generateCacheKey(string $propertyId, string $startDate, string $endDate): string
    {
        return CacheKey::forProperty($propertyId, $startDate, $endDate);
    }

    /**
     * Generate rate limit key for property.
     */
    protected function generateRateLimitKey(string $propertyId): string
    {
        return "analytics_fetch_{$propertyId}";
    }

    /**
     * Check if cache is still fresh.
     */
    protected function isCacheStillFresh($cacheTimestamp): bool
    {
        if (! $cacheTimestamp) {
            return false;
        }

        $age = now()->diffInMinutes($cacheTimestamp);

        // During peak hours, cache expires faster
        if ($this->isPeakHours()) {
            return $age < config('analytics.smart_cache.peak_hours_freshness', 10);
        }

        // Outside peak hours, cache lasts longer
        return $age < config('analytics.smart_cache.off_peak_freshness', 30);
    }

    /**
     * Get dynamic cache duration based on time and property settings.
     */
    protected function getDynamicCacheDuration(GaProperty $property): int
    {
        // Use property's sync frequency as base
        $baseDuration = $property->sync_frequency;

        // Peak hours: use shorter cache
        if ($this->isPeakHours()) {
            return max($this->minCacheDuration, min($baseDuration, 10));
        }

        // Weekend: use longer cache
        if (now()->isWeekend()) {
            return min($this->maxCacheDuration, $baseDuration * 2);
        }

        // Normal hours
        return $baseDuration;
    }

    /**
     * Check if current time is peak hours.
     */
    protected function isPeakHours(): bool
    {
        $hour = now()->hour;
        $start = config('analytics.smart_cache.peak_hours_start', 9);
        $end = config('analytics.smart_cache.peak_hours_end', 17);

        return $hour >= $start && $hour <= $end && ! now()->isWeekend();
    }

    /**
     * Get max attempts per property based on rate limit.
     */
    protected function getMaxAttemptsPerProperty(GaProperty $property): int
    {
        $rateLimitPerHour = config('analytics.smart_cache.rate_limit_per_hour', 120);

        // Convert hourly rate limit to per-sync-frequency limit
        // For 10min sync_frequency: ceil(120 / 6) = 20 attempts per 10 minutes
        return (int) ceil($rateLimitPerHour / (60 / $property->sync_frequency));
    }

    /**
     * Try to find subset data from larger cached period.
     *
     * This method checks if there's a larger period cache that contains
     * the requested date range, and extracts a subset from it.
     */
    protected function findSubsetFromLargerCache(
        string $propertyId,
        string $requestStart,
        string $requestEnd
    ): ?array {
        // Common larger periods to check (30, 60, 90 days)
        $largerPeriods = [30, 60, 90];
        $today = now();

        foreach ($largerPeriods as $days) {
            $largeStart = $today->copy()->subDays($days)->format('Y-m-d');
            $largeEnd = $today->format('Y-m-d');
            $largeCacheKey = $this->generateCacheKey($propertyId, $largeStart, $largeEnd);

            $largeCache = Cache::get($largeCacheKey);
            $largeCacheTimestamp = Cache::get(CacheKey::timestamp($largeCacheKey));

            // Skip if cache doesn't exist or is stale
            if (! $largeCache || ! $this->isCacheStillFresh($largeCacheTimestamp)) {
                continue;
            }

            // Check if requested period is within cached period
            if ($requestStart >= $largeStart && $requestEnd <= $largeEnd) {
                // Extract subset from the larger cache
                return $this->extractSubsetFromRows($largeCache, $requestStart, $requestEnd);
            }
        }

        return null;
    }

    /**
     * Extract subset of data from larger cached period.
     *
     * Filters rows by date range and recalculates totals.
     */
    protected function extractSubsetFromRows(
        array $cachedData,
        string $startDate,
        string $endDate
    ): array {
        // If no rows data, return as-is
        if (! isset($cachedData['rows']) || empty($cachedData['rows'])) {
            return $cachedData;
        }

        // Convert dates to comparable format (YYYYMMDD)
        $start = str_replace('-', '', $startDate);
        $end = str_replace('-', '', $endDate);

        // Filter rows to match requested date range
        $filteredRows = array_filter($cachedData['rows'], function ($row) use ($start, $end) {
            $rowDate = (string) $row['date'];

            return $rowDate >= $start && $rowDate <= $end;
        });

        // Recalculate totals from filtered rows
        $totals = $this->calculateTotalsFromRows(array_values($filteredRows));

        // Return subset data with recalculated totals
        return [
            'totals' => $totals,
            'rows' => array_values($filteredRows),
            // Note: top_pages, traffic_sources, devices are not filtered
            // as they would need separate GA API calls to be accurate for subset
        ];
    }

    /**
     * Clear cache for a specific property.
     * Clears common date range caches (7, 14, 30, 90 days).
     */
    public function clearPropertyCache(GaProperty $property): void
    {
        $today = now();
        $commonPeriods = [7, 14, 30, 90, 180, 365];

        foreach ($commonPeriods as $days) {
            $startDate = $today->copy()->subDays($days)->format('Y-m-d');
            $endDate = $today->format('Y-m-d');
            $cacheKey = CacheKey::forProperty($property->property_id, $startDate, $endDate);

            // Clear all related cache keys
            foreach (CacheKey::getAllKeys($cacheKey) as $key) {
                Cache::forget($key);
            }
        }

        \Log::info('Cleared property cache', [
            'property_id' => $property->property_id,
            'periods_cleared' => count($commonPeriods),
        ]);
    }

    /**
     * Clear all analytics cache for common aggregate periods.
     * Only clears analytics-specific cache, not application-wide cache.
     */
    public function clearAllCache(): void
    {
        $today = now();
        $commonPeriods = [7, 14, 30, 90, 180, 365];

        // Clear aggregate cache for common periods
        foreach ($commonPeriods as $days) {
            $startDate = $today->copy()->subDays($days)->format('Y-m-d');
            $endDate = $today->format('Y-m-d');
            $cacheKey = CacheKey::forAggregate(null, $startDate, $endDate);

            // Clear all related cache keys
            foreach (CacheKey::getAllKeys($cacheKey) as $key) {
                Cache::forget($key);
            }
        }

        // Clear property-specific cache for all active properties
        $properties = GaProperty::active()->get();
        foreach ($properties as $property) {
            $this->clearPropertyCache($property);
        }

        \Log::info('Cleared all analytics cache', [
            'aggregate_periods_cleared' => count($commonPeriods),
            'properties_cleared' => $properties->count(),
        ]);
    }
}
