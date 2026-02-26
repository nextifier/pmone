<?php

namespace App\Jobs;

use App\Models\GaProperty;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refresh property-specific analytics cache in background.
 * Prevents memory leaks by not capturing service instances in closures.
 * Octane-safe: All services are resolved fresh in handle() method.
 */
class RefreshPropertyCache implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $propertyId,
        public string $startDate,
        public string $endDate,
        public string $cacheKey,
        public string $refreshingKey,
        public int $cacheDuration
    ) {
        $this->onQueue('analytics');
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "refresh-property-{$this->cacheKey}";
    }

    /**
     * Execute the job.
     * All services are resolved here (not in constructor) to prevent memory leaks.
     */
    public function handle(): void
    {
        try {
            Log::info("Background property cache refresh started for {$this->cacheKey}");

            // Load property
            $property = GaProperty::find($this->propertyId);

            if (! $property) {
                Log::warning('Property not found for cache refresh', [
                    'property_id' => $this->propertyId,
                ]);

                return;
            }

            // Resolve AnalyticsDataFetcher fresh from container
            $dataFetcher = app(\App\Services\GoogleAnalytics\AnalyticsDataFetcher::class);

            // Create period
            $period = \App\Services\GoogleAnalytics\Period::create(
                $this->startDate,
                $this->endDate
            );

            // Fetch fresh data using the callback pattern
            $freshData = $dataFetcher->fetchDashboardData($property, $period);

            // Store with configured duration
            Cache::put($this->cacheKey, $freshData, now()->addMinutes($this->cacheDuration));
            Cache::put($this->cacheKey.':timestamp', now(), now()->addMinutes($this->cacheDuration));

            Log::info('Property cache refreshed successfully', [
                'cache_key' => $this->cacheKey,
                'property_id' => $this->propertyId,
            ]);
        } catch (Throwable $e) {
            Log::error('Background property cache refresh failed', [
                'cache_key' => $this->cacheKey,
                'property_id' => $this->propertyId,
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
        Log::error('RefreshPropertyCache job failed permanently', [
            'cache_key' => $this->cacheKey,
            'property_id' => $this->propertyId,
            'error' => $exception->getMessage(),
        ]);

        // Clean up refreshing key
        Cache::forget($this->refreshingKey);
    }
}
