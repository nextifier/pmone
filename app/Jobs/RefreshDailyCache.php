<?php

namespace App\Jobs;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsDataFetcher;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refresh 365-day daily analytics cache in background.
 * Prevents memory leaks by not capturing service instances in closures.
 * Octane-safe: All services are resolved fresh in handle() method.
 */
class RefreshDailyCache implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes for 365 days of data

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $propertyId,
        public string $cacheKey,
        public string $timestampKey,
        public string $refreshingKey,
        public int $ttl
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "refresh-daily-{$this->propertyId}";
    }

    /**
     * Execute the job.
     * All services are resolved here (not in constructor) to prevent memory leaks.
     */
    public function handle(AnalyticsDataFetcher $dataFetcher): void
    {
        try {
            Log::info('Background daily cache refresh started', [
                'property_id' => $this->propertyId,
                'cache_key' => $this->cacheKey,
            ]);

            // Load property
            $property = GaProperty::find($this->propertyId);

            if (! $property) {
                Log::warning('Property not found for daily cache refresh', [
                    'property_id' => $this->propertyId,
                ]);

                return;
            }

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

            Cache::put($this->cacheKey, $freshData, now()->addMinutes($this->ttl));
            Cache::put($this->timestampKey, now(), now()->addMinutes($this->ttl));

            Log::info('Daily cache refreshed successfully', [
                'property_id' => $this->propertyId,
                'cache_key' => $this->cacheKey,
                'rows_count' => count($freshData['rows'] ?? []),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to refresh daily data in background', [
                'property_id' => $this->propertyId,
                'cache_key' => $this->cacheKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to allow retry
        } finally {
            Cache::forget($this->refreshingKey);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('RefreshDailyCache job failed permanently', [
            'property_id' => $this->propertyId,
            'cache_key' => $this->cacheKey,
            'error' => $exception->getMessage(),
        ]);

        // Clean up refreshing key
        Cache::forget($this->refreshingKey);
    }
}
