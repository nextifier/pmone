<?php

namespace App\Services\GoogleAnalytics;

use Illuminate\Support\Facades\Cache;

/**
 * Analytics cache wrapper with tagging support.
 * Gracefully falls back to non-tagged cache if driver doesn't support tagging.
 */
class AnalyticsCache
{
    /**
     * Analytics cache tags.
     */
    public const TAG_ANALYTICS = 'analytics';

    public const TAG_PROPERTY = 'analytics:property';

    public const TAG_AGGREGATE = 'analytics:aggregate';

    /**
     * Check if cache driver supports tagging.
     */
    protected static function supportsTagging(): bool
    {
        $driver = config('cache.default');

        return in_array($driver, ['redis', 'memcached']);
    }

    /**
     * Get cache instance with tags if supported.
     */
    public static function tags(array $tags): mixed
    {
        if (self::supportsTagging()) {
            return Cache::tags($tags);
        }

        // Fallback to regular cache if tagging not supported
        return Cache::store();
    }

    /**
     * Put value in cache with tags.
     */
    public static function put(array $tags, string $key, mixed $value, $ttl = null): bool
    {
        if (self::supportsTagging()) {
            return Cache::tags($tags)->put($key, $value, $ttl);
        }

        return Cache::put($key, $value, $ttl);
    }

    /**
     * Get value from cache with tags.
     */
    public static function get(array $tags, string $key, mixed $default = null): mixed
    {
        if (self::supportsTagging()) {
            return Cache::tags($tags)->get($key, $default);
        }

        return Cache::get($key, $default);
    }

    /**
     * Check if key exists in cache with tags.
     */
    public static function has(array $tags, string $key): bool
    {
        if (self::supportsTagging()) {
            return Cache::tags($tags)->has($key);
        }

        return Cache::has($key);
    }

    /**
     * Forget key from cache with tags.
     */
    public static function forget(array $tags, string $key): bool
    {
        if (self::supportsTagging()) {
            return Cache::tags($tags)->forget($key);
        }

        return Cache::forget($key);
    }

    /**
     * Flush all cache for specific tags.
     * Falls back to targeted key deletion if tagging not supported.
     */
    public static function flush(array $tags): bool
    {
        if (self::supportsTagging()) {
            return Cache::tags($tags)->flush();
        }

        // Fallback: can't flush by tags, so we do nothing
        // Individual keys should be managed manually
        \Log::warning('Cache flush by tags requested but driver does not support tagging', [
            'tags' => $tags,
            'driver' => config('cache.default'),
        ]);

        return false;
    }

    /**
     * Get tags for a property cache operation.
     */
    public static function propertyTags(string $propertyId): array
    {
        return [
            self::TAG_ANALYTICS,
            self::TAG_PROPERTY,
            self::TAG_PROPERTY.':'.$propertyId,
        ];
    }

    /**
     * Get tags for an aggregate cache operation.
     */
    public static function aggregateTags(?array $propertyIds = null): array
    {
        $tags = [
            self::TAG_ANALYTICS,
            self::TAG_AGGREGATE,
        ];

        // Add property-specific tags if provided
        if ($propertyIds) {
            foreach ($propertyIds as $propertyId) {
                $tags[] = self::TAG_PROPERTY.':'.$propertyId;
            }
        }

        return $tags;
    }

    /**
     * Clear all analytics cache.
     * Uses tags if supported, otherwise falls back to manual clearing.
     */
    public static function clearAll(): bool
    {
        if (self::supportsTagging()) {
            return Cache::tags([self::TAG_ANALYTICS])->flush();
        }

        \Log::info('Clearing analytics cache without tag support - manual clearing required');

        return false;
    }

    /**
     * Clear cache for specific property.
     */
    public static function clearProperty(string $propertyId): bool
    {
        if (self::supportsTagging()) {
            return Cache::tags([self::TAG_PROPERTY.':'.$propertyId])->flush();
        }

        \Log::info('Clearing property cache without tag support - manual clearing required', [
            'property_id' => $propertyId,
        ]);

        return false;
    }
}
