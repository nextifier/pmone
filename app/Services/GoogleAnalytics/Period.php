<?php

namespace App\Services\GoogleAnalytics;

use Carbon\Carbon;

/**
 * Date range period for analytics queries.
 *
 * Replacement for Spatie\Analytics\Period to remove external dependency.
 * This class provides a simple way to define date ranges for analytics data fetching.
 */
class Period
{
    /**
     * Create a new Period instance.
     */
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate
    ) {}

    /**
     * Create period from number of days ago until today.
     *
     * @param  int  $days  Number of days to go back from today
     * @return self
     */
    public static function days(int $days): self
    {
        return new self(
            startDate: now()->subDays($days)->startOfDay(),
            endDate: now()->endOfDay()
        );
    }

    /**
     * Create period from specific date range.
     *
     * @param  Carbon|string  $startDate  Start date of the period
     * @param  Carbon|string  $endDate  End date of the period
     * @return self
     */
    public static function create(Carbon|string $startDate, Carbon|string $endDate): self
    {
        return new self(
            startDate: $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate),
            endDate: $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate)
        );
    }

    /**
     * Get the start date of the period.
     */
    public function getStartDate(): Carbon
    {
        return $this->startDate;
    }

    /**
     * Get the end date of the period.
     */
    public function getEndDate(): Carbon
    {
        return $this->endDate;
    }
}
