<?php

namespace App\Services\GoogleAnalytics;

use Illuminate\Support\Collection;
use Spatie\Analytics\Period;

class AnalyticsAggregator
{
    public function __construct(
        protected AnalyticsDataFetcher $dataFetcher
    ) {}

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

                if (isset($data['data']['totals'])) {
                    $totals = $data['data']['totals'];

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
        return [
            'metrics' => $this->aggregateMetrics($properties, $period),
            'top_pages' => $this->aggregateTopPages($properties, $period, 20),
            'traffic_sources' => $this->aggregateTrafficSources($properties, $period),
            'devices' => $this->aggregateDevices($properties, $period),
            'period' => [
                'start_date' => $period->startDate->format('Y-m-d'),
                'end_date' => $period->endDate->format('Y-m-d'),
            ],
            'properties_count' => $properties->count(),
        ];
    }
}
