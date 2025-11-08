<?php

namespace App\Services\GoogleAnalytics\Concerns;

/**
 * Trait for calculating totals from analytics rows data.
 * Provides reusable logic for aggregating metrics across multiple rows.
 */
trait CalculatesTotalsFromRows
{
    /**
     * Calculate totals from rows data.
     *
     * Sums up metrics across all rows to generate aggregate totals.
     * Rate and duration metrics are averaged instead of summed.
     *
     * @param  array  $rows  Array of data rows, each containing metrics
     * @return array Aggregated totals with metrics summed or averaged appropriately
     */
    protected function calculateTotalsFromRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $totals = [];
        $firstRow = reset($rows);

        // Initialize totals for each metric (excluding 'date')
        foreach (array_keys($firstRow) as $key) {
            if ($key !== 'date') {
                $totals[$key] = 0;
            }
        }

        // Identify metrics that should be averaged instead of summed
        $averageMetrics = $this->getAverageMetrics($firstRow);

        // Sum up values from all rows
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                if (isset($row[$key])) {
                    // Skip average metrics during summing (we'll calculate them separately)
                    if (! in_array($key, $averageMetrics)) {
                        $totals[$key] += $row[$key];
                    }
                }
            }
        }

        // Calculate averages for rate and duration metrics
        $rowCount = count($rows);
        if ($rowCount > 0) {
            foreach ($averageMetrics as $metric) {
                if (isset($totals[$metric])) {
                    $sum = array_sum(array_column($rows, $metric));
                    $totals[$metric] = $sum / $rowCount;
                }
            }
        }

        return $totals;
    }

    /**
     * Get list of metrics that should be averaged instead of summed.
     *
     * @param  array  $row  Sample row to detect metric types
     * @return array List of metric keys that should be averaged
     */
    protected function getAverageMetrics(array $row): array
    {
        $averageMetrics = [];

        foreach (array_keys($row) as $key) {
            // Metrics containing 'Rate' or 'Duration' should be averaged
            if (str_contains($key, 'Rate') || str_contains($key, 'Duration')) {
                $averageMetrics[] = $key;
            }
        }

        // Additional known average metrics
        $knownAverageMetrics = ['engagementRate', 'averageSessionDuration', 'bounceRate'];

        return array_unique(array_merge($averageMetrics, $knownAverageMetrics));
    }
}
