<?php

namespace App\Jobs;

use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pre-compute analytics for named periods (today, yesterday, this_week, etc).
 * Ensures instant response when users select these periods in the frontend.
 * Runs every 15 minutes via scheduler.
 */
class PreComputeNamedPeriodAnalytics implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Named periods to pre-compute.
     */
    public const NAMED_PERIODS = [
        'today',
        'yesterday',
        'this_week',
        'last_week',
        'this_month',
        'last_month',
        'this_year',
    ];

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes for all periods

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 900; // 15 minutes

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'pre-compute-named-periods';
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        Log::info('Starting pre-compute for named period analytics');

        $successCount = 0;
        $failCount = 0;

        foreach (self::NAMED_PERIODS as $namedPeriod) {
            try {
                // Create period using Period class static methods
                $period = match ($namedPeriod) {
                    'today' => Period::today(),
                    'yesterday' => Period::yesterday(),
                    'this_week' => Period::thisWeek(),
                    'last_week' => Period::lastWeek(),
                    'this_month' => Period::thisMonth(),
                    'last_month' => Period::lastMonth(),
                    'this_year' => Period::thisYear(),
                    default => null,
                };

                if (! $period) {
                    Log::warning('Unknown named period', ['period' => $namedPeriod]);

                    continue;
                }

                Log::info('Computing analytics for named period', [
                    'period' => $namedPeriod,
                    'start_date' => $period->startDate->toDateString(),
                    'end_date' => $period->endDate->toDateString(),
                ]);

                // Fetch and cache aggregated analytics for this period
                // This will populate the cache so users get instant response
                $result = $analyticsService->getAggregatedAnalytics($period, null);

                Log::info('Named period analytics cached successfully', [
                    'period' => $namedPeriod,
                    'properties_count' => $result['properties_count'] ?? 0,
                    'successful_fetches' => $result['successful_fetches'] ?? 0,
                ]);

                $successCount++;
            } catch (Throwable $e) {
                $failCount++;

                Log::error('Failed to compute analytics for named period', [
                    'period' => $namedPeriod,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Continue with other periods instead of failing completely
            }
        }

        Log::info('Finished pre-computing named period analytics', [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'total_periods' => count(self::NAMED_PERIODS),
        ]);

        // Only throw if all periods failed
        if ($failCount > 0 && $successCount === 0) {
            throw new \RuntimeException('All named period computations failed');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('PreComputeNamedPeriodAnalytics job failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
