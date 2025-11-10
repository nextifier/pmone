<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use Carbon\Carbon;

/**
 * Centralized cache key generation for analytics data.
 * Ensures consistent cache key format across the application.
 */
class AnalyticsCacheKeyGenerator
{
    /**
     * Cache version to prevent corruption when data format changes.
     * Increment this when making breaking changes to cached data structure.
     */
    public const VERSION = 'v2';

    /**
     * Cache key prefix for all analytics data.
     */
    public const PREFIX = 'analytics';

    /**
     * Generate cache key for a single property's analytics data.
     */
    public static function forProperty(
        string|GaProperty $property,
        string|Carbon $startDate,
        string|Carbon $endDate
    ): string {
        $propertyId = $property instanceof GaProperty ? $property->property_id : $property;
        $start = $startDate instanceof Carbon ? $startDate->format('Y-m-d') : $startDate;
        $end = $endDate instanceof Carbon ? $endDate->format('Y-m-d') : $endDate;

        return sprintf('%s:%s:property:%s:%s:%s', self::PREFIX, self::VERSION, $propertyId, $start, $end);
    }

    /**
     * Generate cache key for aggregate analytics data.
     */
    public static function forAggregate(
        ?array $propertyIds,
        string|Carbon $startDate,
        string|Carbon $endDate
    ): string {
        // Sort property IDs to prevent cache key collision (e.g., [1,2,3] vs [12,3])
        $key = $propertyIds ? implode(',', collect($propertyIds)->sort()->values()->all()) : 'all';
        $start = $startDate instanceof Carbon ? $startDate->format('Y-m-d') : $startDate;
        $end = $endDate instanceof Carbon ? $endDate->format('Y-m-d') : $endDate;

        return sprintf('%s:%s:aggregate:%s:%s:%s', self::PREFIX, self::VERSION, $key, $start, $end);
    }

    /**
     * Generate cache key with suffix (e.g., for timestamp or metadata).
     */
    public static function withSuffix(string $baseKey, string $suffix): string
    {
        return "{$baseKey}:{$suffix}";
    }

    /**
     * Get cache key for timestamp tracking.
     */
    public static function timestamp(string $baseKey): string
    {
        return self::withSuffix($baseKey, 'timestamp');
    }

    /**
     * Get cache key for refreshing state.
     */
    public static function refreshing(string $baseKey): string
    {
        return self::withSuffix($baseKey, 'refreshing');
    }

    /**
     * Get cache key for last successful data (long-term fallback).
     */
    public static function lastSuccess(string $baseKey): string
    {
        return self::withSuffix($baseKey, 'last_success');
    }

    /**
     * Get all related cache keys for a base key.
     *
     * @return array<string>
     */
    public static function getAllKeys(string $baseKey): array
    {
        return [
            $baseKey,
            self::timestamp($baseKey),
            self::refreshing($baseKey),
            self::lastSuccess($baseKey),
        ];
    }
}
