<?php

namespace App\Jobs;

use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AggregateAnalyticsData implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 120, 300];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?array $propertyIds = null,
        public int $days = 7,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        try {
            $period = Period::days($this->days);

            Log::info('Starting analytics aggregation', [
                'property_ids' => $this->propertyIds,
                'days' => $this->days,
            ]);

            $aggregatedData = $analyticsService->getAggregatedAnalytics($period, $this->propertyIds);

            // Cache the aggregated data
            $cacheKey = $this->generateCacheKey();
            $cacheDuration = now()->addMinutes(15);

            Cache::put($cacheKey, $aggregatedData, $cacheDuration);

            Log::info('Analytics aggregation completed successfully', [
                'properties_count' => $aggregatedData['properties_count'] ?? 0,
                'cache_key' => $cacheKey,
                'cache_until' => $cacheDuration->toDateTimeString(),
            ]);
        } catch (Throwable $e) {
            Log::error('Exception while aggregating analytics data', [
                'property_ids' => $this->propertyIds,
                'days' => $this->days,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to allow retry
        }
    }

    /**
     * Generate cache key for aggregated data.
     */
    protected function generateCacheKey(): string
    {
        if ($this->propertyIds) {
            $propertiesKey = implode('_', $this->propertyIds);

            return "ga4_aggregated_{$propertiesKey}_{$this->days}days";
        }

        return "ga4_aggregated_all_{$this->days}days";
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('AggregateAnalyticsData job failed permanently', [
            'property_ids' => $this->propertyIds,
            'days' => $this->days,
            'error' => $exception->getMessage(),
        ]);
    }
}
