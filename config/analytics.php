<?php

return [

    /*
     * The property id of which you want to display data.
     */
    'property_id' => env('ANALYTICS_PROPERTY_ID'),

    /*
     * Path to the client secret json file. Take a look at the README of this package
     * to learn how to get this file. You can also pass the credentials as an array
     * instead of a file path.
     */
    'service_account_credentials_json' => storage_path('app/analytics/service-account-credentials.json'),

    /*
     * The amount of minutes the Google API responses will be cached.
     * If you set this to zero, the responses won't be cached at all.
     */
    'cache_lifetime_in_minutes' => 60 * 24,

    /*
     * Here you may configure the "store" that the underlying Google_Client will
     * use to store it's data.  You may also add extra parameters that will
     * be passed on setCacheConfig (see docs for google-api-php-client).
     *
     * Optional parameters: "lifetime", "prefix"
     */
    'cache' => [
        'store' => 'file',
    ],

    /*
     * Smart caching configuration
     * NOTE: With daily aggregation system, these settings are less critical
     * as we fetch 365 days once and aggregate on-demand
     */
    'smart_cache' => [
        /*
         * Minimum cache duration in minutes
         */
        'min_duration' => 10,

        /*
         * Maximum cache duration in minutes
         */
        'max_duration' => 60,

        /*
         * Cache freshness during peak hours (in minutes)
         */
        'peak_hours_freshness' => 15,

        /*
         * Cache freshness during off-peak hours (in minutes)
         */
        'off_peak_freshness' => 60,

        /*
         * Peak hours range (9am to 5pm)
         */
        'peak_hours_start' => 9,
        'peak_hours_end' => 17,

        /*
         * Rate limit per property per hour
         */
        'rate_limit_per_hour' => 100,
    ],

    /*
     * API retry configuration for transient failures
     */
    'retry' => [
        /*
         * Maximum number of retry attempts
         */
        'max_attempts' => 3,

        /*
         * Retry delay in seconds (exponential backoff)
         */
        'delays' => [1, 2, 4],
    ],

    /*
     * Chunking configuration for large datasets
     */
    'chunking' => [
        /*
         * Number of properties to process per chunk
         */
        'properties_per_chunk' => 100,

        /*
         * Threshold to trigger chunking
         */
        'chunk_threshold' => 100,
    ],

    /*
     * API request timeout in seconds
     */
    'timeout' => 120,

    /*
     * Rate limiting configuration for endpoints
     */
    'rate_limiting' => [
        /*
         * Maximum number of requests per minute
         */
        'requests_per_minute' => 60,

        /*
         * Maximum number of sync requests per hour
         */
        'sync_requests_per_hour' => 2,
    ],
];
