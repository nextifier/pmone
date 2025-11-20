<?php

namespace App\Jobs;

use App\Models\AnalyticsSyncLog;
use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsAggregator;
use App\Services\GoogleAnalytics\AnalyticsCacheKeyGenerator as CacheKey;
use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refresh aggregate analytics cache in background.
 * Prevents memory leaks by not capturing service instances in closures.
 * Octane-safe: All services are resolved fresh in handle() method.
 */
class RefreshAggregateCache implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 180;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Period $period,
        public ?array $propertyIds,
        public string $cacheKey,
        public int $days,
        public string $refreshingKey
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "refresh-aggregate-{$this->cacheKey}";
    }

    /**
     * Execute the job.
     * All services are resolved here (not in constructor) to prevent memory leaks.
     */
    public function handle(
        AnalyticsAggregator $aggregator,
        AnalyticsService $analyticsService
    ): void {
        // Create sync log entry for aggregate dashboard sync
        $syncLog = AnalyticsSyncLog::startSync(
            syncType: 'aggregate',
            gaPropertyId: null, // null for aggregate syncs
            days: $this->days,
            jobId: $this->job?->getJobId()
        );

        try {
            Log::info("Background refresh started for {$this->cacheKey}", [
                'sync_log_id' => $syncLog->id,
            ]);

            // Build query for properties
            $query = GaProperty::active()->with('project');
            if ($this->propertyIds) {
                $query->whereIn('property_id', $this->propertyIds);
            }

            $totalCount = $query->count();

            if ($totalCount === 0) {
                Log::warning('No active properties found for background refresh');
                $syncLog->markFailed('No active properties found');

                return;
            }

            Log::info("Fetching dashboard data for {$totalCount} properties");

            // For large datasets, process in chunks to prevent memory issues
            $chunkThreshold = config('analytics.chunking.chunk_threshold', 100);
            if ($totalCount > $chunkThreshold) {
                // Process in chunks
                $chunkSize = config('analytics.chunking.properties_per_chunk', 100);
                $aggregatedData = null;

                Log::info("Processing {$totalCount} properties in chunks of {$chunkSize}");

                $query->chunk($chunkSize, function ($properties) use ($aggregator, &$aggregatedData) {
                    $chunkData = $aggregator->getDashboardData($properties, $this->period);

                    if ($aggregatedData === null) {
                        $aggregatedData = $chunkData;
                    } else {
                        // Merge chunk data with aggregated data
                        $aggregatedData = $aggregator->mergeAggregatedData($aggregatedData, $chunkData);
                    }
                });

                $data = $aggregatedData;
            } else {
                // For small datasets, fetch all at once
                $properties = $query->get();
                $data = $aggregator->getDashboardData($properties, $this->period);
            }

            // Store with 30-minute expiry
            Cache::put($this->cacheKey, $data, now()->addMinutes(30));
            Cache::put(CacheKey::timestamp($this->cacheKey), now(), now()->addMinutes(30));

            // Store as "last known good data" that never expires (for instant fallback)
            $lastSuccessKey = CacheKey::lastSuccess($this->cacheKey);
            Cache::put($lastSuccessKey, $data, now()->addYears(10));

            // Mark sync as successful with metadata
            $syncLog->markSuccess([
                'properties_count' => $totalCount,
                'has_totals' => ! empty($data['totals'] ?? []),
                'cache_key' => $this->cacheKey,
            ]);

            Log::info('Aggregate cache refreshed successfully', [
                'cache_key' => $this->cacheKey,
                'last_success_key' => $lastSuccessKey,
                'properties_count' => $totalCount,
                'has_totals' => ! empty($data['totals'] ?? []),
                'sync_log_id' => $syncLog->id,
            ]);
        } catch (Throwable $e) {
            // Mark sync as failed
            $syncLog->markFailed($e->getMessage(), [
                'cache_key' => $this->cacheKey,
                'exception_class' => get_class($e),
            ]);

            Log::error('Background aggregate cache refresh failed', [
                'cache_key' => $this->cacheKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sync_log_id' => $syncLog->id,
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
        Log::error('RefreshAggregateCache job failed permanently', [
            'cache_key' => $this->cacheKey,
            'error' => $exception->getMessage(),
        ]);

        // Clean up refreshing key
        Cache::forget($this->refreshingKey);
    }
}
