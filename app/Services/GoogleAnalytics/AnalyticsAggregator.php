<?php

namespace App\Services\GoogleAnalytics;

use App\Services\GoogleAnalytics\Period;
use Illuminate\Support\Collection;

class AnalyticsAggregator
{
    public function __construct(
        protected AnalyticsDataFetcher $dataFetcher
    ) {}

    /**
     * Calculate totals from rows data.
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

        // Sum up all values
        foreach ($rows as $row) {
            foreach ($totals as $key => $value) {
                if (isset($row[$key])) {
                    // For rates (bounceRate), calculate average instead of sum
                    if (str_contains($key, 'Rate') || str_contains($key, 'Duration')) {
                        continue; // We'll calculate average separately
                    }
                    $totals[$key] += $row[$key];
                }
            }
        }

        // Calculate average for rate and duration metrics
        foreach (array_keys($firstRow) as $key) {
            if (str_contains($key, 'Rate') || str_contains($key, 'Duration')) {
                $sum = array_sum(array_column($rows, $key));
                $totals[$key] = count($rows) > 0 ? $sum / count($rows) : 0;
            }
        }

        return $totals;
    }

    /**
     * Aggregate metrics from multiple properties.
     */
    public function aggregateMetrics(Collection $properties, Period $period): array
    {
        $aggregated = [
            'activeUsers' => 0,
            'newUsers' => 0,
            'sessions' => 0,
            'screenPageViews' => 0,
            'bounceRate' => 0,
            'averageSessionDuration' => 0,
        ];

        $successfulFetches = 0;
        $errors = [];
        $propertyData = [];

        foreach ($properties as $property) {
            try {
                $data = $this->dataFetcher->fetchMetrics($property, $period);

                // Extract data from cache wrapper if present
                $metricsData = $data['data'] ?? $data;

                // Calculate totals from rows if totals are empty
                if (empty($metricsData['totals']) && ! empty($metricsData['rows'])) {
                    $metricsData['totals'] = $this->calculateTotalsFromRows($metricsData['rows']);
                }

                if (! empty($metricsData['totals'])) {
                    $totals = $metricsData['totals'];

                    $aggregated['activeUsers'] += $totals['activeUsers'] ?? 0;
                    $aggregated['newUsers'] += $totals['newUsers'] ?? 0;
                    $aggregated['sessions'] += $totals['sessions'] ?? 0;
                    $aggregated['screenPageViews'] += $totals['screenPageViews'] ?? 0;
                    $aggregated['bounceRate'] += $totals['bounceRate'] ?? 0;
                    $aggregated['averageSessionDuration'] += $totals['averageSessionDuration'] ?? 0;

                    $successfulFetches++;

                    $propertyData[] = [
                        'property_id' => $property->property_id,
                        'property_name' => $property->name,
                        'metrics' => $totals,
                        'is_fresh' => $data['is_fresh'] ?? false,
                        'cached_at' => $data['cached_at'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Calculate averages
        if ($successfulFetches > 0) {
            $aggregated['bounceRate'] = $aggregated['bounceRate'] / $successfulFetches;
            $aggregated['averageSessionDuration'] = $aggregated['averageSessionDuration'] / $successfulFetches;
        }

        return [
            'aggregated_totals' => $aggregated,
            'property_breakdown' => $propertyData,
            'successful_fetches' => $successfulFetches,
            'total_properties' => $properties->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Aggregate top pages from multiple properties.
     */
    public function aggregateTopPages(Collection $properties, Period $period, int $limit = 10): array
    {
        $allPages = [];
        $errors = [];

        foreach ($properties as $property) {
            try {
                $pages = $this->dataFetcher->fetchTopPages($property, $period, $limit);

                foreach ($pages as $page) {
                    $allPages[] = array_merge($page, [
                        'property_id' => $property->property_id,
                        'property_name' => $property->name,
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Sort by pageviews and take top N
        usort($allPages, fn ($a, $b) => $b['pageviews'] <=> $a['pageviews']);

        return [
            'top_pages' => array_slice($allPages, 0, $limit),
            'total_pages' => count($allPages),
            'errors' => $errors,
        ];
    }

    /**
     * Aggregate traffic sources from multiple properties.
     */
    public function aggregateTrafficSources(Collection $properties, Period $period): array
    {
        $sourcesByKey = [];
        $errors = [];

        foreach ($properties as $property) {
            try {
                $sources = $this->dataFetcher->fetchTrafficSources($property, $period);

                foreach ($sources as $source) {
                    $key = $source['source'].'_'.$source['medium'];

                    if (! isset($sourcesByKey[$key])) {
                        $sourcesByKey[$key] = [
                            'source' => $source['source'],
                            'medium' => $source['medium'],
                            'sessions' => 0,
                            'users' => 0,
                            'properties' => [],
                        ];
                    }

                    $sourcesByKey[$key]['sessions'] += $source['sessions'];
                    $sourcesByKey[$key]['users'] += $source['users'];
                    $sourcesByKey[$key]['properties'][] = [
                        'property_id' => $property->property_id,
                        'property_name' => $property->name,
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Sort by sessions
        $sources = array_values($sourcesByKey);
        usort($sources, fn ($a, $b) => $b['sessions'] <=> $a['sessions']);

        return [
            'traffic_sources' => $sources,
            'total_sources' => count($sources),
            'errors' => $errors,
        ];
    }

    /**
     * Aggregate device data from multiple properties.
     */
    public function aggregateDevices(Collection $properties, Period $period): array
    {
        $devicesByCategory = [];
        $errors = [];

        foreach ($properties as $property) {
            try {
                $devices = $this->dataFetcher->fetchDevices($property, $period);

                foreach ($devices as $device) {
                    $category = $device['device'];

                    if (! isset($devicesByCategory[$category])) {
                        $devicesByCategory[$category] = [
                            'device' => $category,
                            'users' => 0,
                            'sessions' => 0,
                        ];
                    }

                    $devicesByCategory[$category]['users'] += $device['users'];
                    $devicesByCategory[$category]['sessions'] += $device['sessions'];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $devices = array_values($devicesByCategory);

        return [
            'devices' => $devices,
            'errors' => $errors,
        ];
    }

    /**
     * Get comprehensive analytics dashboard data.
     */
    public function getDashboardData(Collection $properties, Period $period): array
    {
        $metricsData = $this->aggregateMetrics($properties, $period);
        $topPagesData = $this->aggregateTopPages($properties, $period, 20);
        $trafficSourcesData = $this->aggregateTrafficSources($properties, $period);
        $devicesData = $this->aggregateDevices($properties, $period);

        return [
            // Frontend expects 'totals' at root level
            'totals' => $metricsData['aggregated_totals'],
            'property_breakdown' => $metricsData['property_breakdown'],
            'successful_fetches' => $metricsData['successful_fetches'],
            'top_pages' => $topPagesData['top_pages'],
            'total_pages' => $topPagesData['total_pages'],
            'traffic_sources' => $trafficSourcesData['traffic_sources'],
            'total_sources' => $trafficSourcesData['total_sources'],
            'devices' => $devicesData['devices'],
            'period' => [
                'start_date' => $period->startDate->format('Y-m-d'),
                'end_date' => $period->endDate->format('Y-m-d'),
            ],
            'properties_count' => $properties->count(),
            'errors' => array_merge(
                $metricsData['errors'] ?? [],
                $topPagesData['errors'] ?? [],
                $trafficSourcesData['errors'] ?? [],
                $devicesData['errors'] ?? []
            ),
        ];
    }
}
