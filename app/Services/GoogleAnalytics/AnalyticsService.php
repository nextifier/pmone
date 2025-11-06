<?php

namespace App\Services\GoogleAnalytics;

use App\Models\GaProperty;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    public function __construct(
        protected AnalyticsDataFetcher $dataFetcher,
        protected AnalyticsAggregator $aggregator,
        protected SmartAnalyticsCache $cache
    ) {}

    /**
     * Get all active GA properties.
     */
    public function getActiveProperties(): Collection
    {
        return GaProperty::active()->get();
    }

    /**
     * Get property by ID.
     */
    public function getProperty(int $id): GaProperty
    {
        return GaProperty::findOrFail($id);
    }

    /**
     * Get property by property_id (GA4 property ID).
     */
    public function getPropertyByGA4Id(string $propertyId): ?GaProperty
    {
        return GaProperty::where('property_id', $propertyId)->first();
    }

    /**
     * Get analytics data for a single property.
     */
    public function getPropertyAnalytics(GaProperty $property, Period $period): array
    {
        $metricsData = $this->dataFetcher->fetchMetrics($property, $period);

        // Extract data from cache wrapper if present
        $metrics = $metricsData['data'] ?? $metricsData;

        // Calculate totals from rows if totals are empty
        if (empty($metrics['totals']) && ! empty($metrics['rows'])) {
            $metrics['totals'] = $this->calculateTotalsFromRows($metrics['rows']);
        }

        return [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'property_id' => $property->property_id,
                'account_name' => $property->account_name,
                'last_synced_at' => $property->last_synced_at,
            ],
            'metrics' => $metrics['totals'] ?? [],
            'rows' => $metrics['rows'] ?? [],
            'top_pages' => $this->dataFetcher->fetchTopPages($property, $period, 10),
            'traffic_sources' => $this->dataFetcher->fetchTrafficSources($property, $period),
            'devices' => $this->dataFetcher->fetchDevices($property, $period),
            'period' => [
                'start_date' => $period->startDate->format('Y-m-d'),
                'end_date' => $period->endDate->format('Y-m-d'),
            ],
        ];
    }

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
                    if (str_contains($key, 'Rate')) {
                        continue; // We'll calculate average separately
                    }
                    $totals[$key] += $row[$key];
                }
            }
        }

        // Calculate average for rate metrics
        foreach (array_keys($firstRow) as $key) {
            if (str_contains($key, 'Rate')) {
                $sum = array_sum(array_column($rows, $key));
                $totals[$key] = count($rows) > 0 ? $sum / count($rows) : 0;
            }
        }

        return $totals;
    }

    /**
     * Get aggregated analytics from all active properties.
     */
    public function getAggregatedAnalytics(Period $period, ?array $propertyIds = null): array
    {
        // Create cache key for aggregate data
        $propertyIdsStr = $propertyIds ? implode(',', $propertyIds) : 'all';
        $cacheKey = "ga4_aggregate_{$propertyIdsStr}_{$period->startDate->format('Y-m-d')}_{$period->endDate->format('Y-m-d')}";

        // Try to get from cache first (cache for 30 minutes)
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($period, $propertyIds) {
            $properties = $propertyIds
                ? GaProperty::active()->whereIn('property_id', $propertyIds)->get()
                : $this->getActiveProperties();

            return $this->aggregator->getDashboardData($properties, $period);
        });
    }

    /**
     * Get aggregated analytics by account.
     */
    public function getAnalyticsByAccount(string $accountName, Period $period): array
    {
        $properties = GaProperty::active()
            ->where('account_name', $accountName)
            ->get();

        return $this->aggregator->getDashboardData($properties, $period);
    }

    /**
     * Sync property data and mark as synced.
     */
    public function syncProperty(GaProperty $property, Period $period): array
    {
        try {
            $data = $this->getPropertyAnalytics($property, $period);
            $property->markAsSynced();

            return [
                'success' => true,
                'property_id' => $property->property_id,
                'property_name' => $property->name,
                'synced_at' => $property->last_synced_at,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'property_id' => $property->property_id,
                'property_name' => $property->name,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync all active properties.
     */
    public function syncAllProperties(Period $period): array
    {
        $properties = $this->getActiveProperties();
        $results = [];

        foreach ($properties as $property) {
            $results[] = $this->syncProperty($property, $period);
        }

        $successful = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();

        return [
            'total' => $properties->count(),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * Sync properties that need syncing based on their sync_frequency.
     */
    public function syncPropertiesNeedingUpdate(Period $period): array
    {
        $properties = GaProperty::needsSync()->get();
        $results = [];

        foreach ($properties as $property) {
            $results[] = $this->syncProperty($property, $period);
        }

        return [
            'properties_needing_sync' => $properties->count(),
            'results' => $results,
        ];
    }

    /**
     * Get cache status for all properties.
     */
    public function getCacheStatus(): array
    {
        $properties = $this->getActiveProperties();
        $status = [];

        foreach ($properties as $property) {
            $status[] = [
                'property_id' => $property->property_id,
                'property_name' => $property->name,
                'last_synced_at' => $property->last_synced_at,
                'needs_sync' => $property->needsSync(),
                'sync_frequency' => $property->sync_frequency,
            ];
        }

        return $status;
    }

    /**
     * Clear cache for specific property.
     */
    public function clearPropertyCache(GaProperty $property): void
    {
        $this->cache->clearPropertyCache($property);
    }

    /**
     * Clear cache for all properties.
     */
    public function clearAllCache(): void
    {
        $this->cache->clearAllCache();
    }

    /**
     * Get accounts grouped by account name.
     */
    public function getGroupedByAccount(): Collection
    {
        return GaProperty::active()
            ->get()
            ->groupBy('account_name')
            ->map(function ($properties, $accountName) {
                return [
                    'account_name' => $accountName,
                    'properties_count' => $properties->count(),
                    'properties' => $properties->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'property_id' => $p->property_id,
                        'last_synced_at' => $p->last_synced_at,
                    ]),
                ];
            })
            ->values();
    }

    /**
     * Create period from days count.
     */
    public function createPeriodFromDays(int $days): Period
    {
        return Period::days($days);
    }

    /**
     * Create period from date range.
     */
    public function createPeriodFromDates(string $startDate, string $endDate): Period
    {
        return Period::create(
            \Carbon\Carbon::parse($startDate),
            \Carbon\Carbon::parse($endDate)
        );
    }
}
