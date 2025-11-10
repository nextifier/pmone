<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\Period;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\RunReportRequest;

class AnalyticsDataFetcher
{
    /**
     * Client pool to reuse GA4 client instances.
     * Prevents creating new clients for every request.
     *
     * @var array<string, BetaAnalyticsDataClient>
     */
    protected static array $clientPool = [];

    public function __construct(
        protected SmartAnalyticsCache $cache,
        protected AnalyticsMetrics $metrics
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
     * Fetch data directly from GA4 API with quota handling and retry logic.
     */
    protected function fetchFromGA4(
        GaProperty $property,
        string $startDate,
        string $endDate,
        array $metrics
    ): array {
        $maxRetries = config('analytics.retry.max_attempts', 3);
        $retryDelays = config('analytics.retry.delays', [1, 2, 4]); // Exponential backoff in seconds

        $startTime = microtime(true);
        $success = false;
        $errorType = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $client = $this->createGA4Client($property);

                $response = $this->runReport($client, $property, $startDate, $endDate, $metrics);

                $result = $this->formatResponse($response, $metrics);

                // Record successful API call
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $this->metrics->recordApiCall($property->property_id, $duration, true, 'runReport');
                $this->metrics->recordQuotaUsage($property->property_id, 1);

                return $result;
            } catch (\Exception $e) {
                // Check if it's a quota error
                if ($this->isQuotaError($e)) {
                    $retryAfter = $this->extractRetryAfter($e);
                    $errorType = 'quota_exceeded';

                    \Log::warning('GA4 API quota exceeded', [
                        'property_id' => $property->property_id,
                        'property_name' => $property->name,
                        'attempt' => $attempt + 1,
                        'retry_after' => $retryAfter,
                        'error' => $e->getMessage(),
                    ]);

                    // If we've exhausted retries, throw quota exception
                    if ($attempt >= $maxRetries) {
                        // Record failed API call
                        $duration = (int) ((microtime(true) - $startTime) * 1000);
                        $this->metrics->recordApiCall($property->property_id, $duration, false, 'runReport', $errorType);

                        throw new \App\Exceptions\Analytics\QuotaExceededException(
                            "Google Analytics API quota exceeded for property {$property->name}",
                            $retryAfter,
                            $property->property_id
                        );
                    }

                    // Wait before retry with exponential backoff
                    $delay = $retryAfter ?? $retryDelays[$attempt];
                    \Log::info("Retrying after {$delay} seconds...");
                    sleep($delay);

                    continue;
                }

                // Check if it's a transient network error
                if ($this->isTransientError($e)) {
                    $errorType = 'transient_error';

                    if ($attempt >= $maxRetries) {
                        // Record failed API call
                        $duration = (int) ((microtime(true) - $startTime) * 1000);
                        $this->metrics->recordApiCall($property->property_id, $duration, false, 'runReport', $errorType);

                        throw new \Exception("Failed to fetch data from GA4 for property {$property->name} after {$maxRetries} retries: {$e->getMessage()}");
                    }

                    \Log::warning('Transient error, retrying...', [
                        'property_id' => $property->property_id,
                        'attempt' => $attempt + 1,
                        'error' => $e->getMessage(),
                    ]);

                    sleep($retryDelays[$attempt]);
                    continue;
                }

                // Non-retryable error
                $errorType = 'fatal_error';
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $this->metrics->recordApiCall($property->property_id, $duration, false, 'runReport', $errorType);

                throw new \Exception("Failed to fetch data from GA4 for property {$property->name}: {$e->getMessage()}");
            }
        }

        // This should never be reached
        $duration = (int) ((microtime(true) - $startTime) * 1000);
        $this->metrics->recordApiCall($property->property_id, $duration, false, 'runReport', 'max_retries_exceeded');

        throw new \Exception("Failed to fetch data from GA4 for property {$property->name}: Maximum retries exceeded");
    }

    /**
     * Check if exception is a quota error.
     */
    protected function isQuotaError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'quota') ||
               str_contains($message, 'rate limit') ||
               str_contains($message, 'too many requests') ||
               str_contains($message, '429');
    }

    /**
     * Check if exception is a transient network error.
     */
    protected function isTransientError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timeout') ||
               str_contains($message, 'connection') ||
               str_contains($message, 'network') ||
               str_contains($message, '503') ||
               str_contains($message, '502') ||
               str_contains($message, '504');
    }

    /**
     * Extract retry-after value from error message.
     */
    protected function extractRetryAfter(\Exception $e): ?int
    {
        $message = $e->getMessage();

        // Try to extract retry-after header value
        if (preg_match('/retry[_\s-]?after[:\s]+(\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Create or reuse GA4 client for specific property.
     * Implements client pooling to prevent creating duplicate instances.
     */
    protected function createGA4Client(GaProperty $property): BetaAnalyticsDataClient
    {
        $credentialsPath = config('analytics.service_account_credentials_json');

        // Use credentials path as pool key since all properties share same credentials
        $poolKey = md5($credentialsPath);

        // Return existing client if available
        if (isset(self::$clientPool[$poolKey])) {
            return self::$clientPool[$poolKey];
        }

        // Create new client and store in pool
        self::$clientPool[$poolKey] = new BetaAnalyticsDataClient([
            'credentials' => $credentialsPath,
        ]);

        \Log::info('Created new GA4 client and added to pool', [
            'pool_key' => $poolKey,
            'pool_size' => count(self::$clientPool),
        ]);

        return self::$clientPool[$poolKey];
    }

    /**
     * Clear the client pool.
     * Useful for testing or when credentials change.
     */
    public static function clearClientPool(): void
    {
        self::$clientPool = [];
        \Log::info('GA4 client pool cleared');
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

    /**
     * Fetch realtime active users (last 30 minutes).
     * Returns the number of users currently active on the property.
     */
    public function fetchRealtimeUsers(GaProperty $property): int
    {
        try {
            $client = $this->createGA4Client($property);

            $request = (new RunRealtimeReportRequest)
                ->setProperty('properties/'.$property->property_id)
                ->setMetrics([
                    new Metric(['name' => 'activeUsers']),
                ]);

            $response = $client->runRealtimeReport($request);

            // Get active users from the first row
            if ($response->getRowCount() > 0) {
                $rows = $response->getRows();
                $firstRow = $rows[0];

                return (int) $firstRow->getMetricValues()[0]->getValue();
            }

            return 0;
        } catch (\Exception $e) {
            \Log::warning("Failed to fetch realtime users for property {$property->name}: {$e->getMessage()}");

            return 0; // Return 0 instead of throwing, since realtime data is not critical
        }
    }
}
