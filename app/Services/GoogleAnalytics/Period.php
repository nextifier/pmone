<?php

namespace App\Services\GoogleAnalytics;

use Carbon\Carbon;

/**
 * Date range period for analytics queries.
 *
 * Replacement for Spatie\Analytics\Period to remove external dependency.
 * This class provides a simple way to define date ranges for analytics data fetching.
 *
 * Supports predefined periods (today, yesterday, last 7 days, etc.) and custom date ranges.
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
     * Create period from number of days ago until today (inclusive).
     *
     * For "Last 7 days": includes today + 6 previous days = 7 total days.
     * Example: Nov 11 (today), Nov 10, Nov 9, Nov 8, Nov 7, Nov 6, Nov 5 = 7 days
     *
     * @param  int  $days  Number of days including today
     */
    public static function days(int $days): self
    {
        return new self(
            startDate: now()->subDays($days - 1)->startOfDay(),
            endDate: now()->endOfDay()
        );
    }

    /**
     * Create period for today only.
     */
    public static function today(): self
    {
        return new self(
            startDate: now()->startOfDay(),
            endDate: now()->endOfDay()
        );
    }

    /**
     * Create period for yesterday only.
     */
    public static function yesterday(): self
    {
        return new self(
            startDate: now()->subDay()->startOfDay(),
            endDate: now()->subDay()->endOfDay()
        );
    }

    /**
     * Create period for this week (Monday to today).
     */
    public static function thisWeek(): self
    {
        return new self(
            startDate: now()->startOfWeek(), // Monday
            endDate: now()->endOfDay()
        );
    }

    /**
     * Create period for last week (Monday to Sunday).
     */
    public static function lastWeek(): self
    {
        return new self(
            startDate: now()->subWeek()->startOfWeek(), // Last Monday
            endDate: now()->subWeek()->endOfWeek() // Last Sunday
        );
    }

    /**
     * Create period for this month (1st to today).
     */
    public static function thisMonth(): self
    {
        return new self(
            startDate: now()->startOfMonth(),
            endDate: now()->endOfDay()
        );
    }

    /**
     * Create period for last month (1st to last day).
     */
    public static function lastMonth(): self
    {
        return new self(
            startDate: now()->subMonth()->startOfMonth(),
            endDate: now()->subMonth()->endOfMonth()
        );
    }

    /**
     * Create period for this year (Jan 1 to today).
     */
    public static function thisYear(): self
    {
        return new self(
            startDate: now()->startOfYear(),
            endDate: now()->endOfDay()
        );
    }

    /**
     * Create period from specific date range.
     *
     * @param  Carbon|string  $startDate  Start date of the period
     * @param  Carbon|string  $endDate  End date of the period
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
