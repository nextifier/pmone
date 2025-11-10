<?php

namespace App\Services\GoogleAnalytics;

use App\Services\GoogleAnalytics\Concerns\CalculatesTotalsFromRows;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Concurrency;

class AnalyticsAggregator
{
    use CalculatesTotalsFromRows;

    public function __construct(
        protected AnalyticsDataFetcher $dataFetcher
    ) {}

    /**
     * Aggregate metrics from multiple properties using parallel execution.
     * This significantly improves performance when dealing with multiple properties.
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

        // Create indexed lookup to avoid N+1 query problem
        $propertiesLookup = $properties->keyBy('property_id');

        // Execute all property fetches in parallel for massive performance gain
        // Use sync driver to avoid serialization issues with process driver
        $tasks = $properties->map(fn ($property) => fn () => (function () use ($property, $period) {
            try {
                $data = $this->dataFetcher->fetchMetrics($property, $period);

                // Extract data from cache wrapper if present
                $metricsData = $data['data'] ?? $data;

                // Calculate totals from rows if totals are empty
                if (empty($metricsData['totals']) && ! empty($metricsData['rows'])) {
                    $metricsData['totals'] = $this->calculateTotalsFromRows($metricsData['rows']);
                }

                return [
                    'success' => true,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'totals' => $metricsData['totals'] ?? [],
                    'is_fresh' => $data['is_fresh'] ?? false,
                    // Use property's individual last_synced_at instead of aggregate cache timestamp
                    // This shows when each property was actually synced, not when the aggregate was cached
                    'cached_at' => $property->last_synced_at?->toIso8601String() ?? ($data['cached_at'] ?? null),
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        })())->all();

        $results = Concurrency::driver('sync')->run($tasks);

        // Process parallel results
        foreach ($results as $result) {
            if ($result['success']) {
                $totals = $result['totals'];

                if (! empty($totals)) {
                    $aggregated['activeUsers'] += $totals['activeUsers'] ?? 0;
                    $aggregated['newUsers'] += $totals['newUsers'] ?? 0;
                    $aggregated['sessions'] += $totals['sessions'] ?? 0;
                    $aggregated['screenPageViews'] += $totals['screenPageViews'] ?? 0;
                    $aggregated['bounceRate'] += $totals['bounceRate'] ?? 0;
                    $aggregated['averageSessionDuration'] += $totals['averageSessionDuration'] ?? 0;

                    $successfulFetches++;

                    // Use indexed lookup instead of firstWhere to avoid N+1 problem
                    $propertyWithProject = $propertiesLookup->get($result['property_id']);

                    $propertyData[] = [
                        'property_id' => $result['property_id'],
                        'property_name' => $result['property_name'],
                        'metrics' => $totals,
                        'is_fresh' => $result['is_fresh'],
                        // cached_at now contains property's last_synced_at, showing individual sync times
                        'cached_at' => $result['cached_at'],
                        'project' => $propertyWithProject && $propertyWithProject->project ? [
                            'id' => $propertyWithProject->project->id,
                            'name' => $propertyWithProject->project->name,
                            'profile_image' => $propertyWithProject->project->getMediaUrls('profile_image'),
                        ] : null,
                    ];
                }
            } else {
                $errors[] = [
                    'property_id' => $result['property_id'],
                    'property_name' => $result['property_name'],
                    'error' => $result['error'],
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
     * Aggregate top pages from multiple properties using parallel execution.
     */
    public function aggregateTopPages(Collection $properties, Period $period, int $limit = 10): array
    {
        $allPages = [];
        $errors = [];

        // Execute all property fetches in parallel
        $tasks = $properties->map(fn ($property) => fn () => (function () use ($property, $period, $limit) {
            try {
                $pages = $this->dataFetcher->fetchTopPages($property, $period, $limit);

                return [
                    'success' => true,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'pages' => $pages,
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        })())->all();

        $results = Concurrency::driver('sync')->run($tasks);

        // Process parallel results
        foreach ($results as $result) {
            if ($result['success']) {
                foreach ($result['pages'] as $page) {
                    $allPages[] = array_merge($page, [
                        'property_id' => $result['property_id'],
                        'property_name' => $result['property_name'],
                    ]);
                }
            } else {
                $errors[] = [
                    'property_id' => $result['property_id'],
                    'property_name' => $result['property_name'],
                    'error' => $result['error'],
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
     * Aggregate traffic sources from multiple properties using parallel execution.
     */
    public function aggregateTrafficSources(Collection $properties, Period $period): array
    {
        $sourcesByKey = [];
        $errors = [];

        // Execute all property fetches in parallel
        $tasks = $properties->map(fn ($property) => fn () => (function () use ($property, $period) {
            try {
                $sources = $this->dataFetcher->fetchTrafficSources($property, $period);

                return [
                    'success' => true,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'sources' => $sources,
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        })())->all();

        $results = Concurrency::driver('sync')->run($tasks);

        // Process parallel results
        foreach ($results as $result) {
            if ($result['success']) {
                foreach ($result['sources'] as $source) {
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
                        'property_id' => $result['property_id'],
                        'property_name' => $result['property_name'],
                    ];
                }
            } else {
                $errors[] = [
                    'property_id' => $result['property_id'],
                    'property_name' => $result['property_name'],
                    'error' => $result['error'],
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
     * Aggregate device data from multiple properties using parallel execution.
     */
    public function aggregateDevices(Collection $properties, Period $period): array
    {
        $devicesByCategory = [];
        $errors = [];

        // Execute all property fetches in parallel
        $tasks = $properties->map(fn ($property) => fn () => (function () use ($property, $period) {
            try {
                $devices = $this->dataFetcher->fetchDevices($property, $period);

                return [
                    'success' => true,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'devices' => $devices,
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'property_id' => $property->property_id,
                    'property_name' => $property->name,
                    'error' => $e->getMessage(),
                ];
            }
        })())->all();

        $results = Concurrency::driver('sync')->run($tasks);

        // Process parallel results
        foreach ($results as $result) {
            if ($result['success']) {
                foreach ($result['devices'] as $device) {
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
            } else {
                $errors[] = [
                    'property_id' => $result['property_id'],
                    'property_name' => $result['property_name'],
                    'error' => $result['error'],
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
     * Get comprehensive analytics dashboard data with parallel execution.
     * Fetches all data types simultaneously for maximum performance.
     */
    public function getDashboardData(Collection $properties, Period $period): array
    {
        // Execute all aggregation tasks in parallel for maximum performance
        // Use sync driver to avoid serialization issues
        [$metricsData, $topPagesData, $trafficSourcesData, $devicesData] = Concurrency::driver('sync')->run([
            fn () => $this->aggregateMetrics($properties, $period),
            fn () => $this->aggregateTopPages($properties, $period, 20),
            fn () => $this->aggregateTrafficSources($properties, $period),
            fn () => $this->aggregateDevices($properties, $period),
        ]);

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
