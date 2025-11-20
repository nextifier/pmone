<?php

namespace App\Jobs;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsDataFetcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pre-compute realtime analytics data in background.
 * Ensures instant response for realtime active users endpoint.
 * Runs every 2 minutes via scheduler.
 */
class RefreshRealtimeAnalytics implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 120; // 2 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?array $propertyIds = null,
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        $propertiesKey = $this->propertyIds ? implode('-', $this->propertyIds) : 'all';

        return "refresh-realtime-{$propertiesKey}";
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsDataFetcher $dataFetcher): void
    {
        try {
            $cacheKey = 'realtime_users:'.($this->propertyIds ? implode(',', $this->propertyIds) : 'all');
            $lastSuccessKey = $cacheKey.':last_success';

            Log::info('Refreshing realtime analytics', [
                'property_ids' => $this->propertyIds,
                'cache_key' => $cacheKey,
            ]);

            // Get active properties
            $query = GaProperty::active();

            if ($this->propertyIds) {
                $query->whereIn('property_id', $this->propertyIds);
            }

            $properties = $query->get();
            $totalActiveUsers = 0;
            $propertyBreakdown = [];

            // Fetch realtime users for each property
            foreach ($properties as $property) {
                try {
                    $activeUsers = $dataFetcher->fetchRealtimeUsers($property);

                    $totalActiveUsers += $activeUsers;

                    if ($activeUsers > 0) {
                        $propertyBreakdown[] = [
                            'property_id' => $property->property_id,
                            'property_name' => $property->name,
                            'active_users' => $activeUsers,
                        ];
                    }
                } catch (Throwable $e) {
                    Log::warning('Failed to fetch realtime users for property', [
                        'property_id' => $property->property_id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other properties
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

            // Cache for 2 minutes (matches schedule frequency)
            Cache::put($cacheKey, $result, now()->addMinutes(2));
            Cache::put($cacheKey.':timestamp', now(), now()->addMinutes(2));

            // Store as long-term fallback
            Cache::put($lastSuccessKey, $result, now()->addYears(10));

            Log::info('Realtime analytics refreshed successfully', [
                'total_active_users' => $totalActiveUsers,
                'properties_count' => $properties->count(),
                'cache_key' => $cacheKey,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to refresh realtime analytics', [
                'property_ids' => $this->propertyIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to allow retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('RefreshRealtimeAnalytics job failed permanently', [
            'property_ids' => $this->propertyIds,
            'error' => $exception->getMessage(),
        ]);
    }
}
