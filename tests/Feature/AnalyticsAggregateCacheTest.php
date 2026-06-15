<?php

use App\Jobs\RefreshAggregateCache;
use App\Models\GaProperty;
use App\Services\GoogleAnalytics\AnalyticsAggregator;
use App\Services\GoogleAnalytics\AnalyticsCacheKeyGenerator as CacheKey;
use App\Services\GoogleAnalytics\AnalyticsService;
use App\Services\GoogleAnalytics\Period;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function fakeAggregateData(): array
{
    return [
        'totals' => [
            'activeUsers' => 1234,
            'newUsers' => 567,
            'sessions' => 2345,
            'screenPageViews' => 8910,
            'bounceRate' => 0.42,
            'averageSessionDuration' => 95,
        ],
        'property_breakdown' => [
            [
                'property_id' => '123456789',
                'property_name' => 'Example Site',
                'metrics' => ['activeUsers' => 1234],
            ],
        ],
        'top_pages' => [],
        'traffic_sources' => [],
        'devices' => [],
        'properties_count' => 1,
    ];
}

it('generates a duration-keyed latest snapshot cache key', function () {
    expect(CacheKey::latestSnapshot(null, 30))->toBe('analytics:v2:aggregate-latest:all:30d');
    expect(CacheKey::latestSnapshot([3, 1, 2], 7))->toBe('analytics:v2:aggregate-latest:1,2,3:7d');
});

it('returns the previous interval snapshot instead of zeros on a cache miss', function () {
    Queue::fake();

    $period = Period::days(30);

    // Only the duration-keyed snapshot exists (exact-date cache + last_success absent),
    // simulating a rolling window that shifted at day rollover.
    Cache::put(CacheKey::latestSnapshot(null, 30), fakeAggregateData(), now()->addDay());

    $result = app(AnalyticsService::class)->getAggregatedAnalytics($period);

    expect($result['totals']['activeUsers'])->toBe(1234)
        ->and($result['property_breakdown'])->toHaveCount(1)
        ->and($result['cache_info']['from_fallback'])->toBeTrue()
        ->and($result['cache_info']['is_updating'])->toBeTrue();

    // A background refresh is dispatched to recompute the current window.
    Queue::assertPushed(RefreshAggregateCache::class);
});

it('returns an empty updating payload when nothing is cached', function () {
    Queue::fake();

    $result = app(AnalyticsService::class)->getAggregatedAnalytics(Period::days(30));

    expect($result['totals']['activeUsers'])->toBe(0)
        ->and($result['property_breakdown'])->toBe([])
        ->and($result['cache_info']['is_updating'])->toBeTrue()
        ->and($result['cache_info']['initial_load'])->toBeTrue();

    Queue::assertPushed(RefreshAggregateCache::class);
});

it('writes the duration-keyed snapshot after a successful aggregate refresh', function () {
    GaProperty::factory()->create(['is_active' => true]);

    $aggregator = mock(AnalyticsAggregator::class);
    $aggregator->shouldReceive('getDashboardData')->andReturn(fakeAggregateData());

    $period = Period::days(30);
    $cacheKey = CacheKey::forAggregate(null, $period->startDate, $period->endDate);

    $job = new RefreshAggregateCache(
        period: $period,
        propertyIds: null,
        cacheKey: $cacheKey,
        days: 30,
        refreshingKey: CacheKey::refreshing($cacheKey),
    );

    $job->handle($aggregator, app(AnalyticsService::class));

    expect(Cache::has(CacheKey::latestSnapshot(null, 30)))->toBeTrue()
        ->and(Cache::get(CacheKey::latestSnapshot(null, 30))['totals']['activeUsers'])->toBe(1234);
});
