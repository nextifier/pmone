<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SmartAnalyticsCache
{
    protected int $minCacheDuration = 5; // 5 minutes minimum

    protected int $maxCacheDuration = 30; // 30 minutes maximum

    /**
     * Get data with smart caching strategy.
     */
    public function getDataWithSmartCache(
        GaProperty $property,
        string $startDate,
        string $endDate,
        callable $fetchCallback
    ): array {
        $cacheKey = $this->generateCacheKey($property->property_id, $startDate, $endDate);
        $rateLimitKey = $this->generateRateLimitKey($property->property_id);

        // Check if data exists in cache
        $cachedData = Cache::get($cacheKey);
        $cacheTimestamp = Cache::get("{$cacheKey}_timestamp");

        if ($cachedData && $this->isCacheStillFresh($cacheTimestamp)) {
            return [
                'data' => $cachedData,
                'cached_at' => $cacheTimestamp,
                'is_fresh' => true,
            ];
        }

        // Rate limiting: prevent too many requests
        $maxAttempts = $this->getMaxAttemptsPerProperty($property);
        $decayMinutes = $property->sync_frequency;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            // Return stale cache if available
            if ($cachedData) {
                return [
                    'data' => $cachedData,
                    'cached_at' => $cacheTimestamp,
                    'is_fresh' => false,
                    'message' => 'Rate limited, showing cached data',
                ];
            }

            $availableIn = RateLimiter::availableIn($rateLimitKey);

            throw new \Exception("Rate limit exceeded. Available in {$availableIn} seconds.");
        }

        // Fetch new data
        RateLimiter::hit($rateLimitKey, $decayMinutes * 60); // Convert to seconds

        $freshData = $fetchCallback();

        // Dynamic cache duration
        $cacheDuration = $this->getDynamicCacheDuration($property);

        Cache::put($cacheKey, $freshData, now()->addMinutes($cacheDuration));
        Cache::put("{$cacheKey}_timestamp", now(), now()->addMinutes($cacheDuration));

        return [
            'data' => $freshData,
            'cached_at' => now(),
            'is_fresh' => true,
        ];
    }

    /**
     * Generate cache key for analytics data.
     */
    protected function generateCacheKey(string $propertyId, string $startDate, string $endDate): string
    {
        return "ga4_{$propertyId}_{$startDate}_{$endDate}";
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

        // During peak hours (9am-5pm), cache expires faster
        if ($this->isPeakHours()) {
            return $age < 10; // 10 minutes (increased from 5)
        }

        // Outside peak hours, cache lasts longer
        return $age < 30; // 30 minutes (increased from 15)
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

        return $hour >= 9 && $hour <= 17 && ! now()->isWeekend();
    }

    /**
     * Get max attempts per property based on rate limit.
     */
    protected function getMaxAttemptsPerProperty(GaProperty $property): int
    {
        // Convert hourly rate limit to per-sync-frequency limit
        return (int) ceil($property->rate_limit_per_hour / (60 / $property->sync_frequency));
    }

    /**
     * Clear cache for a specific property.
     */
    public function clearPropertyCache(GaProperty $property): void
    {
        $pattern = "ga4_{$property->property_id}_*";
        Cache::forget($pattern);
    }

    /**
     * Clear all analytics cache.
     */
    public function clearAllCache(): void
    {
        Cache::flush();
    }
}
