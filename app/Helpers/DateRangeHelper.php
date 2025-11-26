<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateRangeHelper
{
    /**
     * Convert period string to date range
     *
     * Supported periods:
     * - today
     * - yesterday
     * - this_week
     * - last_week
     * - this_month
     * - last_month
     * - this_year
     * - numeric values (e.g., "7", "30", "90", "365") for last N days
     */
    public static function getDateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'yesterday' => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay(),
            ],
            'this_week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'last_week' => [
                'start' => $now->copy()->subWeek()->startOfWeek(),
                'end' => $now->copy()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'last_month' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            default => is_numeric($period)
                ? [
                    'start' => $now->copy()->subDays((int) $period)->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ]
                : [
                    'start' => $now->copy()->subDays(7)->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ],
        };
    }

    /**
     * Get label for period
     */
    public static function getPeriodLabel(string $period): string
    {
        return match ($period) {
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This week',
            'last_week' => 'Last week',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_year' => 'This year',
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            '365' => 'Last 365 days',
            default => is_numeric($period) ? "Last {$period} days" : 'Last 7 days',
        };
    }
}
