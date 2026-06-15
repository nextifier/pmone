<?php

namespace App\Jobs;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pre-warm the aggregate analytics cache for the dashboard's common date ranges.
 *
 * The scheduler already keeps the "today" aggregate, per-property daily data, and
 * realtime users warm. It does NOT warm the rolling ranges the dashboard loads by
 * default (7 / 30 / 90 days), so the first visit after the cache expires used to
 * return an empty zero payload while a background job computed the real numbers.
 *
 * This job calls the same aggregation path the controller uses for each common range
 * (with comparison, matching the frontend default), which warms both the current and
 * previous-period caches. Aggregation reads the already-cached per-property daily data,
 * so it does not add Google Analytics API calls.
 */
class WarmAggregateCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The date ranges (in days, including today) the dashboard loads most often.
     *
     * @var array<int>
     */
    public const RANGES = [7, 30, 90];

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public array $backoff = [30, 60, 120];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 900; // 15 minutes

    public function __construct()
    {
        $this->onQueue('analytics');
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'warm-aggregate-cache-'.now()->format('Y-m-d-H-i');
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        if (! GaProperty::active()->exists()) {
            Log::info('No active properties found for aggregate cache warming');

            return;
        }

        foreach (self::RANGES as $days) {
            try {
                $period = Period::days($days);

                // Mirrors the controller default (with_comparison=true), which warms both
                // the current and previous period caches the dashboard relies on.
                $analyticsService->getAggregatedAnalyticsWithComparison($period);
            } catch (Throwable $e) {
                Log::warning('Failed to warm aggregate cache for range', [
                    'days' => $days,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('WarmAggregateCacheJob failed permanently', [
            'error' => $exception->getMessage(),
        ]);
    }
}
