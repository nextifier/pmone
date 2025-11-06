<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Spatie\Analytics\Analytics;
use Spatie\Analytics\Period;

class AnalyticsDataFetcher
{
    public function __construct(
        protected SmartAnalyticsCache $cache
    ) {}

    /**
     * Fetch analytics data for a specific property with caching.
     */
    public function fetchPropertyData(
        GaProperty $property,
        string $startDate,
        string $endDate,
        array $metrics = ['pageviews', 'users', 'sessions']
    ): array {
        return $this->cache->getDataWithSmartCache(
            $property,
            $startDate,
            $endDate,
            fn () => $this->fetchFromGA4($property, $startDate, $endDate, $metrics)
        );
    }

    /**
     * Fetch data directly from GA4 API.
     */
    protected function fetchFromGA4(
        GaProperty $property,
        string $startDate,
        string $endDate,
        array $metrics
    ): array {
        try {
            $client = $this->createGA4Client($property);

            $response = $this->runReport($client, $property, $startDate, $endDate, $metrics);

            return $this->formatResponse($response, $metrics);
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch data from GA4 for property {$property->name}: {$e->getMessage()}");
        }
    }

    /**
     * Create GA4 client for specific property.
     */
    protected function createGA4Client(GaProperty $property): BetaAnalyticsDataClient
    {
        $credentialsPath = config('analytics.service_account_credentials_json');

        return new BetaAnalyticsDataClient([
            'credentials' => $credentialsPath,
        ]);
    }

    /**
     * Run analytics report.
     */
    protected function runReport(
        BetaAnalyticsDataClient $client,
        GaProperty $property,
        string $startDate,
        string $endDate,
        array $metrics
    ): mixed {
        $request = (new RunReportRequest)
            ->setProperty('properties/'.$property->property_id)
            ->setDateRanges([
                new DateRange([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]),
            ])
            ->setDimensions([
                new Dimension(['name' => 'date']),
            ])
            ->setMetrics(array_map(fn ($metric) => new Metric(['name' => $metric]), $metrics));

        return $client->runReport($request);
    }

    /**
     * Format GA4 API response.
     */
    protected function formatResponse($response, array $metrics): array
    {
        $data = [
            'rows' => [],
            'totals' => [],
        ];

        // Process rows
        foreach ($response->getRows() as $row) {
            $rowData = [
                'date' => $row->getDimensionValues()[0]->getValue(),
            ];

            foreach ($metrics as $index => $metric) {
                $rowData[$metric] = (int) $row->getMetricValues()[$index]->getValue();
            }

            $data['rows'][] = $rowData;
        }

        // Process totals
        if ($response->getTotals()->count() > 0) {
            $totals = $response->getTotals()[0];
            foreach ($metrics as $index => $metric) {
                $data['totals'][$metric] = (int) $totals->getMetricValues()[$index]->getValue();
            }
        }

        return $data;
    }

    /**
     * Fetch multiple metrics for property.
     */
    public function fetchMetrics(GaProperty $property, Period $period): array
    {
        $startDate = $period->startDate->format('Y-m-d');
        $endDate = $period->endDate->format('Y-m-d');

        return $this->fetchPropertyData($property, $startDate, $endDate, [
            'activeUsers',
            'newUsers',
            'sessions',
            'screenPageViews',
            'bounceRate',
            'averageSessionDuration',
        ]);
    }

    /**
     * Fetch top pages for property.
     */
    public function fetchTopPages(GaProperty $property, Period $period, int $limit = 10): array
    {
        try {
            $client = $this->createGA4Client($property);

            $request = (new RunReportRequest)
                ->setProperty('properties/'.$property->property_id)
                ->setDateRanges([
                    new DateRange([
                        'start_date' => $period->startDate->format('Y-m-d'),
                        'end_date' => $period->endDate->format('Y-m-d'),
                    ]),
                ])
                ->setDimensions([
                    new Dimension(['name' => 'pageTitle']),
                    new Dimension(['name' => 'pagePath']),
                ])
                ->setMetrics([
                    new Metric(['name' => 'screenPageViews']),
                ])
                ->setLimit($limit);

            $response = $client->runReport($request);

            $pages = [];
            foreach ($response->getRows() as $row) {
                $pages[] = [
                    'title' => $row->getDimensionValues()[0]->getValue(),
                    'path' => $row->getDimensionValues()[1]->getValue(),
                    'pageviews' => (int) $row->getMetricValues()[0]->getValue(),
                ];
            }

            return $pages;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch top pages: {$e->getMessage()}");
        }
    }

    /**
     * Fetch traffic sources for property.
     */
    public function fetchTrafficSources(GaProperty $property, Period $period): array
    {
        try {
            $client = $this->createGA4Client($property);

            $request = (new RunReportRequest)
                ->setProperty('properties/'.$property->property_id)
                ->setDateRanges([
                    new DateRange([
                        'start_date' => $period->startDate->format('Y-m-d'),
                        'end_date' => $period->endDate->format('Y-m-d'),
                    ]),
                ])
                ->setDimensions([
                    new Dimension(['name' => 'sessionSource']),
                    new Dimension(['name' => 'sessionMedium']),
                ])
                ->setMetrics([
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'activeUsers']),
                ]);

            $response = $client->runReport($request);

            $sources = [];
            foreach ($response->getRows() as $row) {
                $sources[] = [
                    'source' => $row->getDimensionValues()[0]->getValue(),
                    'medium' => $row->getDimensionValues()[1]->getValue(),
                    'sessions' => (int) $row->getMetricValues()[0]->getValue(),
                    'users' => (int) $row->getMetricValues()[1]->getValue(),
                ];
            }

            return $sources;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch traffic sources: {$e->getMessage()}");
        }
    }

    /**
     * Fetch device categories for property.
     */
    public function fetchDevices(GaProperty $property, Period $period): array
    {
        try {
            $client = $this->createGA4Client($property);

            $request = (new RunReportRequest)
                ->setProperty('properties/'.$property->property_id)
                ->setDateRanges([
                    new DateRange([
                        'start_date' => $period->startDate->format('Y-m-d'),
                        'end_date' => $period->endDate->format('Y-m-d'),
                    ]),
                ])
                ->setDimensions([
                    new Dimension(['name' => 'deviceCategory']),
                ])
                ->setMetrics([
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'sessions']),
                ]);

            $response = $client->runReport($request);

            $devices = [];
            foreach ($response->getRows() as $row) {
                $devices[] = [
                    'device' => $row->getDimensionValues()[0]->getValue(),
                    'users' => (int) $row->getMetricValues()[0]->getValue(),
                    'sessions' => (int) $row->getMetricValues()[1]->getValue(),
                ];
            }

            return $devices;
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch device data: {$e->getMessage()}");
        }
    }
}
