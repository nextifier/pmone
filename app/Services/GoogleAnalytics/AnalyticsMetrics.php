<?php

namespace App\Services\GoogleAnalytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Analytics Metrics Tracking Service.
 *
 * Tracks API calls, cache performance, quota usage, and system health metrics
 * for the Google Analytics integration.
 *
 * @package App\Services\GoogleAnalytics
 */
class AnalyticsMetrics
{
    protected const METRICS_PREFIX = 'analytics_metrics';

    protected const RETENTION_DAYS = 30;

    /**
     * Record an API call to Google Analytics.
     */
    public function recordApiCall(
        string $propertyId,
        int $durationMs,
        bool $success,
        ?string $endpoint = null,
        ?string $errorType = null
    ): void {
        $timestamp = now();

        DB::table('analytics_metrics')->insert([
            'property_id' => $propertyId,
            'metric_type' => 'api_call',
            'metric_value' => $durationMs,
            'metadata' => json_encode([
                'success' => $success,
                'endpoint' => $endpoint,
                'error_type' => $errorType,
            ]),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        // Update hourly counters in cache for quick access
        $hourKey = $this->getHourlyKey('api_calls', $propertyId);
        Cache::increment($hourKey);
        Cache::expire($hourKey, 3600); // 1 hour

        if (! $success) {
            $errorKey = $this->getHourlyKey('api_errors', $propertyId);
            Cache::increment($errorKey);
            Cache::expire($errorKey, 3600);
        }
    }

    /**
     * Record cache hit.
     */
    public function recordCacheHit(string $cacheType, ?string $propertyId = null): void
    {
        $key = $this->getHourlyKey('cache_hits', $propertyId ?? 'aggregate');
        Cache::increment($key);
        Cache::expire($key, 3600);

        // Store in database for historical analysis
        DB::table('analytics_metrics')->insert([
            'property_id' => $propertyId,
            'metric_type' => 'cache_hit',
            'metric_value' => 1,
            'metadata' => json_encode(['cache_type' => $cacheType]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Record cache miss.
     */
    public function recordCacheMiss(string $cacheType, ?string $propertyId = null): void
    {
        $key = $this->getHourlyKey('cache_misses', $propertyId ?? 'aggregate');
        Cache::increment($key);
        Cache::expire($key, 3600);

        // Store in database
        DB::table('analytics_metrics')->insert([
            'property_id' => $propertyId,
            'metric_type' => 'cache_miss',
            'metric_value' => 1,
            'metadata' => json_encode(['cache_type' => $cacheType]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Record quota usage.
     */
    public function recordQuotaUsage(string $propertyId, int $tokens): void
    {
        $dailyKey = $this->getDailyKey('quota_usage', $propertyId);

        Cache::increment($dailyKey, $tokens);
        Cache::expire($dailyKey, 86400); // 24 hours

        // Store in database
        DB::table('analytics_metrics')->insert([
            'property_id' => $propertyId,
            'metric_type' => 'quota_usage',
            'metric_value' => $tokens,
            'metadata' => json_encode(['date' => now()->format('Y-m-d')]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get API call statistics for a property.
     */
    public function getApiCallStats(?string $propertyId = null, int $hours = 24): array
    {
        $query = DB::table('analytics_metrics')
            ->where('metric_type', 'api_call')
            ->where('created_at', '>=', now()->subHours($hours));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $calls = $query->get();

        $totalCalls = $calls->count();
        $successfulCalls = $calls->filter(function ($call) {
            $metadata = json_decode($call->metadata, true);

            return $metadata['success'] ?? false;
        })->count();

        $avgDuration = $calls->avg('metric_value');
        $maxDuration = $calls->max('metric_value');

        return [
            'total_calls' => $totalCalls,
            'successful_calls' => $successfulCalls,
            'failed_calls' => $totalCalls - $successfulCalls,
            'success_rate' => $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100, 2) : 0,
            'avg_duration_ms' => round($avgDuration ?? 0, 2),
            'max_duration_ms' => $maxDuration ?? 0,
            'period_hours' => $hours,
        ];
    }

    /**
     * Get cache performance statistics.
     */
    public function getCacheStats(?string $propertyId = null, int $hours = 24): array
    {
        $query = DB::table('analytics_metrics')
            ->whereIn('metric_type', ['cache_hit', 'cache_miss'])
            ->where('created_at', '>=', now()->subHours($hours));

        if ($propertyId) {
            $query->where('property_id', $propertyId);
        }

        $metrics = $query->get();

        $hits = $metrics->where('metric_type', 'cache_hit')->count();
        $misses = $metrics->where('metric_type', 'cache_miss')->count();
        $total = $hits + $misses;

        return [
            'cache_hits' => $hits,
            'cache_misses' => $misses,
            'total_requests' => $total,
            'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
            'miss_rate' => $total > 0 ? round(($misses / $total) * 100, 2) : 0,
            'period_hours' => $hours,
        ];
    }

    /**
     * Get quota usage for a property.
     */
    public function getQuotaUsage(string $propertyId, int $days = 1): array
    {
        $query = DB::table('analytics_metrics')
            ->where('metric_type', 'quota_usage')
            ->where('property_id', $propertyId)
            ->where('created_at', '>=', now()->subDays($days));

        $totalUsage = $query->sum('metric_value');

        // GA4 has 25,000 requests per day per property limit
        $dailyLimit = 25000;
        $usagePercentage = ($totalUsage / $dailyLimit) * 100;

        return [
            'property_id' => $propertyId,
            'total_usage' => $totalUsage,
            'daily_limit' => $dailyLimit,
            'usage_percentage' => round($usagePercentage, 2),
            'remaining' => max(0, $dailyLimit - $totalUsage),
            'period_days' => $days,
        ];
    }

    /**
     * Get system health metrics.
     */
    public function getSystemHealth(): array
    {
        $last24Hours = now()->subHours(24);

        // API health
        $apiStats = $this->getApiCallStats(null, 24);

        // Cache health
        $cacheStats = $this->getCacheStats(null, 24);

        // Recent errors
        $recentErrors = DB::table('analytics_metrics')
            ->where('metric_type', 'api_call')
            ->where('created_at', '>=', $last24Hours)
            ->get()
            ->filter(function ($call) {
                $metadata = json_decode($call->metadata, true);

                return ! ($metadata['success'] ?? true);
            })
            ->count();

        // Active properties
        $activeProperties = DB::table('ga_properties')
            ->where('is_active', true)
            ->count();

        return [
            'status' => $this->determineSystemStatus($apiStats['success_rate'], $cacheStats['hit_rate']),
            'api_health' => [
                'success_rate' => $apiStats['success_rate'],
                'avg_response_time_ms' => $apiStats['avg_duration_ms'],
                'total_calls_24h' => $apiStats['total_calls'],
            ],
            'cache_health' => [
                'hit_rate' => $cacheStats['hit_rate'],
                'total_requests_24h' => $cacheStats['total_requests'],
            ],
            'error_count_24h' => $recentErrors,
            'active_properties' => $activeProperties,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Determine overall system status.
     */
    protected function determineSystemStatus(float $successRate, float $cacheHitRate): string
    {
        if ($successRate >= 95 && $cacheHitRate >= 80) {
            return 'healthy';
        }

        if ($successRate >= 85 && $cacheHitRate >= 60) {
            return 'degraded';
        }

        return 'unhealthy';
    }

    /**
     * Clean up old metrics data.
     */
    public function cleanupOldMetrics(): int
    {
        $cutoffDate = now()->subDays(self::RETENTION_DAYS);

        return DB::table('analytics_metrics')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get hourly cache key.
     */
    protected function getHourlyKey(string $metric, string $identifier): string
    {
        $hour = now()->format('Y-m-d-H');

        return sprintf('%s:%s:%s:%s', self::METRICS_PREFIX, $metric, $identifier, $hour);
    }

    /**
     * Get daily cache key.
     */
    protected function getDailyKey(string $metric, string $identifier): string
    {
        $date = now()->format('Y-m-d');

        return sprintf('%s:%s:%s:%s', self::METRICS_PREFIX, $metric, $identifier, $date);
    }

    /**
     * Get metrics summary for dashboard.
     */
    public function getDashboardMetrics(): array
    {
        return [
            'api_stats' => $this->getApiCallStats(null, 24),
            'cache_stats' => $this->getCacheStats(null, 24),
            'system_health' => $this->getSystemHealth(),
            'top_properties_by_api_calls' => $this->getTopPropertiesByApiCalls(10),
        ];
    }

    /**
     * Get top properties by API call volume.
     */
    protected function getTopPropertiesByApiCalls(int $limit = 10): array
    {
        return DB::table('analytics_metrics')
            ->select('property_id', DB::raw('COUNT(*) as call_count'))
            ->where('metric_type', 'api_call')
            ->where('created_at', '>=', now()->subHours(24))
            ->whereNotNull('property_id')
            ->groupBy('property_id')
            ->orderByDesc('call_count')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'property_id' => $row->property_id,
                    'call_count' => $row->call_count,
                ];
            })
            ->toArray();
    }
}
