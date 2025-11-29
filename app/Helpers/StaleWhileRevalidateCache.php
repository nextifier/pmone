<?php

namespace App\Helpers;

use Closure;
use Illuminate\Support\Facades\Cache;

class StaleWhileRevalidateCache
{
    /**
     * Get cached data with stale-while-revalidate strategy.
     *
     * Returns stale data immediately while refreshing in background.
     *
     * @param  string  $key  Cache key
     * @param  array<string>  $tags  Cache tags for invalidation
     * @param  int  $staleTtl  Seconds before data is considered stale (triggers background refresh)
     * @param  int  $maxTtl  Seconds before data expires completely (hard limit)
     * @param  Closure  $callback  Function to generate fresh data
     * @param  string|null  $refreshJobClass  Optional job class to dispatch for background refresh
     */
    public static function remember(
        string $key,
        array $tags,
        int $staleTtl,
        int $maxTtl,
        Closure $callback,
        ?string $refreshJobClass = null,
        array $refreshJobParams = []
    ): mixed {
        $dataKey = "swr:{$key}:data";
        $timestampKey = "swr:{$key}:timestamp";
        $refreshingKey = "swr:{$key}:refreshing";

        $cache = Cache::tags($tags);

        // Try to get cached data
        $cachedData = $cache->get($dataKey);
        $cachedTimestamp = $cache->get($timestampKey);

        // If no cached data, fetch fresh data synchronously
        if ($cachedData === null) {
            return self::fetchAndStore($cache, $dataKey, $timestampKey, $maxTtl, $callback);
        }

        // Check if data is stale
        $isStale = $cachedTimestamp === null || (now()->timestamp - $cachedTimestamp) > $staleTtl;

        if ($isStale) {
            // Check if already refreshing to prevent stampede
            $isRefreshing = $cache->get($refreshingKey);

            if (! $isRefreshing) {
                // Mark as refreshing (lock for 30 seconds)
                $cache->put($refreshingKey, true, 30);

                // Dispatch background job if provided
                if ($refreshJobClass && class_exists($refreshJobClass)) {
                    dispatch(new $refreshJobClass($key, $tags, $maxTtl, $refreshJobParams));
                } else {
                    // Refresh synchronously but after response (using terminate middleware would be ideal)
                    // For now, dispatch to queue with default job
                    dispatch(function () use ($cache, $dataKey, $timestampKey, $refreshingKey, $maxTtl, $callback) {
                        try {
                            $freshData = $callback();
                            $cache->put($dataKey, $freshData, $maxTtl);
                            $cache->put($timestampKey, now()->timestamp, $maxTtl);
                        } finally {
                            $cache->forget($refreshingKey);
                        }
                    })->afterResponse();
                }
            }
        }

        // Return cached data (stale or fresh)
        return $cachedData;
    }

    /**
     * Fetch fresh data and store in cache.
     */
    private static function fetchAndStore($cache, string $dataKey, string $timestampKey, int $maxTtl, Closure $callback): mixed
    {
        $freshData = $callback();

        $cache->put($dataKey, $freshData, $maxTtl);
        $cache->put($timestampKey, now()->timestamp, $maxTtl);

        return $freshData;
    }

    /**
     * Manually refresh cache data (called by background job).
     */
    public static function refresh(
        string $key,
        array $tags,
        int $maxTtl,
        Closure $callback
    ): mixed {
        $dataKey = "swr:{$key}:data";
        $timestampKey = "swr:{$key}:timestamp";
        $refreshingKey = "swr:{$key}:refreshing";

        $cache = Cache::tags($tags);

        try {
            $freshData = $callback();
            $cache->put($dataKey, $freshData, $maxTtl);
            $cache->put($timestampKey, now()->timestamp, $maxTtl);

            return $freshData;
        } finally {
            $cache->forget($refreshingKey);
        }
    }

    /**
     * Invalidate cache for a key.
     */
    public static function forget(string $key, array $tags): void
    {
        $cache = Cache::tags($tags);

        $cache->forget("swr:{$key}:data");
        $cache->forget("swr:{$key}:timestamp");
        $cache->forget("swr:{$key}:refreshing");
    }
}
