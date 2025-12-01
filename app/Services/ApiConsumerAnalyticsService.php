<?php

namespace App\Services;

use App\Models\ApiConsumer;
use App\Models\ApiConsumerRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApiConsumerAnalyticsService
{
    private const STATUS_DISTRIBUTION_SQL = "
        CASE
            WHEN status_code >= 200 AND status_code < 300 THEN '2xx'
            WHEN status_code >= 300 AND status_code < 400 THEN '3xx'
            WHEN status_code >= 400 AND status_code < 500 THEN '4xx'
            WHEN status_code >= 500 THEN '5xx'
            ELSE 'unknown'
        END as status_group,
        COUNT(*) as count
    ";

    /**
     * Parse and validate the days parameter
     */
    public function parseDays(int $days): int
    {
        return min(max($days, 1), 90);
    }

    /**
     * Get the start date based on days
     */
    public function getStartDate(int $days): Carbon
    {
        return now()->subDays($days)->startOfDay();
    }

    /**
     * Build a base query with optional consumer filter
     */
    private function baseQuery(?int $consumerId, Carbon $startDate): Builder
    {
        $query = ApiConsumerRequest::query()
            ->where('created_at', '>=', $startDate);

        if ($consumerId !== null) {
            $query->where('api_consumer_id', $consumerId);
        }

        return $query;
    }

    /**
     * Get summary statistics
     *
     * @return array{total_requests: int, successful_requests: int, failed_requests: int, success_rate: float, avg_response_time: float, max_response_time: int, min_response_time: int, active_consumers?: int}
     */
    public function getSummary(?int $consumerId, Carbon $startDate, bool $includeConsumerCount = false): array
    {
        $selectParts = [
            'COUNT(*) as total_requests',
            'COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as successful_requests',
            'COUNT(CASE WHEN status_code >= 400 THEN 1 END) as failed_requests',
            'AVG(response_time_ms) as avg_response_time',
        ];

        if ($consumerId !== null) {
            $selectParts[] = 'MAX(response_time_ms) as max_response_time';
            $selectParts[] = 'MIN(response_time_ms) as min_response_time';
        }

        if ($includeConsumerCount) {
            $selectParts[] = 'COUNT(DISTINCT api_consumer_id) as active_consumers';
        }

        $summary = $this->baseQuery($consumerId, $startDate)
            ->selectRaw(implode(', ', $selectParts))
            ->first();

        $totalRequests = (int) ($summary->total_requests ?? 0);
        $successfulRequests = (int) ($summary->successful_requests ?? 0);

        $result = [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => (int) ($summary->failed_requests ?? 0),
            'success_rate' => $totalRequests > 0
                ? round(($successfulRequests / $totalRequests) * 100, 2)
                : 0,
            'avg_response_time' => round($summary->avg_response_time ?? 0, 2),
        ];

        if ($consumerId !== null) {
            $result['max_response_time'] = (int) ($summary->max_response_time ?? 0);
            $result['min_response_time'] = (int) ($summary->min_response_time ?? 0);
        }

        if ($includeConsumerCount) {
            $result['active_consumers'] = (int) ($summary->active_consumers ?? 0);
        }

        return $result;
    }

    /**
     * Get requests per day for chart
     *
     * @return Collection<int, array{date: string, count: int}>
     */
    public function getRequestsPerDay(?int $consumerId, Carbon $startDate): Collection
    {
        return $this->baseQuery($consumerId, $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($item) => [
                'date' => $item->date,
                'count' => (int) $item->count,
            ]);
    }

    /**
     * Get status code distribution
     *
     * @return array{2xx: int, 3xx: int, 4xx: int, 5xx: int}
     */
    public function getStatusDistribution(?int $consumerId, Carbon $startDate): array
    {
        $distribution = $this->baseQuery($consumerId, $startDate)
            ->selectRaw(self::STATUS_DISTRIBUTION_SQL)
            ->groupBy('status_group')
            ->get()
            ->pluck('count', 'status_group');

        return [
            '2xx' => (int) ($distribution['2xx'] ?? 0),
            '3xx' => (int) ($distribution['3xx'] ?? 0),
            '4xx' => (int) ($distribution['4xx'] ?? 0),
            '5xx' => (int) ($distribution['5xx'] ?? 0),
        ];
    }

    /**
     * Get top endpoints for a specific consumer
     *
     * @return Collection<int, array{endpoint: string, method: string, count: int, avg_time: float}>
     */
    public function getTopEndpoints(int $consumerId, Carbon $startDate, int $limit = 10): Collection
    {
        return $this->baseQuery($consumerId, $startDate)
            ->selectRaw('endpoint, method, COUNT(*) as count, AVG(response_time_ms) as avg_time')
            ->groupBy('endpoint', 'method')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'endpoint' => $item->endpoint,
                'method' => $item->method,
                'count' => (int) $item->count,
                'avg_time' => round($item->avg_time ?? 0, 2),
            ]);
    }

    /**
     * Get hourly distribution for today
     *
     * @return Collection<int, array{hour: int, count: int}>
     */
    public function getHourlyDistribution(int $consumerId): Collection
    {
        $driver = DB::connection()->getDriverName();
        $hourExpression = match ($driver) {
            'sqlite' => "strftime('%H', created_at)",
            'mysql', 'mariadb' => 'HOUR(created_at)',
            default => 'EXTRACT(HOUR FROM created_at)', // PostgreSQL
        };

        return ApiConsumerRequest::query()
            ->where('api_consumer_id', $consumerId)
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw("{$hourExpression} as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn ($item) => [
                'hour' => (int) $item->hour,
                'count' => (int) $item->count,
            ]);
    }

    /**
     * Get top consumers by request count (optimized - no N+1)
     *
     * @return Collection<int, array{id: int, name: string, website_url: string, request_count: int, avg_time: float}>
     */
    public function getTopConsumers(Carbon $startDate, int $limit = 5): Collection
    {
        $consumerIds = ApiConsumerRequest::query()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('api_consumer_id, COUNT(*) as request_count, AVG(response_time_ms) as avg_time')
            ->groupBy('api_consumer_id')
            ->orderByDesc('request_count')
            ->limit($limit)
            ->get();

        if ($consumerIds->isEmpty()) {
            return collect();
        }

        // Eager load all consumers in one query to avoid N+1
        $consumers = ApiConsumer::whereIn('id', $consumerIds->pluck('api_consumer_id'))
            ->get()
            ->keyBy('id');

        return $consumerIds->map(fn ($item) => [
            'id' => $item->api_consumer_id,
            'name' => $consumers->get($item->api_consumer_id)?->name ?? 'Unknown',
            'website_url' => $consumers->get($item->api_consumer_id)?->website_url ?? '',
            'request_count' => (int) $item->request_count,
            'avg_time' => round($item->avg_time ?? 0, 2),
        ]);
    }

    /**
     * Build period data array
     *
     * @return array{days: int, start_date: string, end_date: string}
     */
    public function buildPeriodData(int $days, Carbon $startDate): array
    {
        return [
            'days' => $days,
            'start_date' => $startDate->toDateString(),
            'end_date' => now()->toDateString(),
        ];
    }
}
