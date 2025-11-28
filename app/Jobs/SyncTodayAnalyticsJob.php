<?php

namespace App\Jobs;

use App\Models\AnalyticsSyncLog;
use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync today's analytics data for all active properties.
 *
 * This job proactively fetches and caches today's data in the background,
 * ensuring instant loading when users request the "today" period.
 *
 * Optimizations:
 * - Runs every 15 minutes to keep data fresh
 * - Uses ShouldBeUnique to prevent duplicate syncs
 * - Fetches data for all properties in a single job
 * - Updates aggregate cache for instant dashboard loading
 */
class SyncTodayAnalyticsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [30, 60, 120];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes for all properties

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 900; // 15 minutes

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'sync-today-analytics-'.now()->format('Y-m-d-H');
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        Log::info('Starting today analytics sync job');

        $properties = GaProperty::active()->get();

        if ($properties->isEmpty()) {
            Log::info('No active properties found for today sync');

            return;
        }

        // Create sync log entry
        $syncLog = AnalyticsSyncLog::startSync(
            syncType: 'today',
            days: 1,
            jobId: $this->job?->getJobId()
        );

        try {
            $period = Period::today();
            $successful = 0;
            $failed = 0;
            $errors = [];

            Log::info('Syncing today data for properties', [
                'properties_count' => $properties->count(),
                'period' => [
                    'start_date' => $period->startDate->format('Y-m-d'),
                    'end_date' => $period->endDate->format('Y-m-d'),
                ],
            ]);

            // Sync each property's today data
            foreach ($properties as $property) {
                try {
                    $result = $analyticsService->syncProperty($property, $period);

                    if ($result['success']) {
                        $successful++;
                        Log::debug('Today data synced for property', [
                            'property_id' => $property->property_id,
                            'property_name' => $property->name,
                        ]);
                    } else {
                        $failed++;
                        $errors[] = [
                            'property_id' => $property->property_id,
                            'property_name' => $property->name,
                            'error' => $result['error'] ?? 'Unknown error',
                        ];
                    }
                } catch (Throwable $e) {
                    $failed++;
                    $errors[] = [
                        'property_id' => $property->property_id,
                        'property_name' => $property->name,
                        'error' => $e->getMessage(),
                    ];

                    Log::warning('Failed to sync today data for property', [
                        'property_id' => $property->property_id,
                        'property_name' => $property->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Force refresh aggregate cache for today period
            // This ensures the dashboard shows fresh data immediately
            try {
                Log::info('Refreshing aggregate cache for today');
                $analyticsService->getAggregatedAnalytics($period);
            } catch (Throwable $e) {
                Log::warning('Failed to refresh aggregate cache for today', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Mark sync as successful if at least one property synced
            if ($successful > 0) {
                $syncLog->markSuccess([
                    'successful' => $successful,
                    'failed' => $failed,
                    'total' => $properties->count(),
                    'errors' => $errors,
                ]);

                Log::info('Today analytics sync completed', [
                    'successful' => $successful,
                    'failed' => $failed,
                    'total' => $properties->count(),
                    'sync_log_id' => $syncLog->id,
                ]);
            } else {
                $syncLog->markFailed('All properties failed to sync', [
                    'successful' => $successful,
                    'failed' => $failed,
                    'total' => $properties->count(),
                    'errors' => $errors,
                ]);

                Log::error('All properties failed to sync today data', [
                    'errors' => $errors,
                    'sync_log_id' => $syncLog->id,
                ]);
            }
        } catch (Throwable $e) {
            $syncLog->markFailed($e->getMessage(), [
                'exception_class' => get_class($e),
            ]);

            Log::error('Exception during today analytics sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sync_log_id' => $syncLog->id,
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SyncTodayAnalyticsJob failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
