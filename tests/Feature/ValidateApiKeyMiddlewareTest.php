<?php

use App\Http\Middleware\ValidateApiKey;
use App\Listeners\MarkResponseCacheHit;
use App\Models\ApiConsumer;
use App\Models\ApiConsumerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\ResponseCache\Events\ResponseCacheHitEvent;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->consumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_middleware_key',
        'is_active' => true,
        'allowed_origins' => null,
    ]);
});

it('accepts the api key via the X-API-Key header', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_middleware_key'])
        ->getJson('/api/public/blog/posts');

    $response->assertSuccessful();
});

it('rejects a key sent only via the api_key query string', function () {
    $response = $this->getJson('/api/public/blog/posts?api_key=pk_test_middleware_key');

    $response->assertStatus(401);
});

it('rejects requests with no key at all', function () {
    $response = $this->getJson('/api/public/blog/posts');

    $response->assertStatus(401);
});

it('still rejects an invalid key sent as a query string even without api.key alias confusion', function () {
    $response = $this->getJson('/api/public/blog/posts?api_key=totally_invalid');

    $response->assertStatus(401);
});

it('flags the request when the response cache serves a hit', function () {
    $request = Request::create('/api/public/blog/posts');

    (new MarkResponseCacheHit)->handle(new ResponseCacheHitEvent($request, 5, []));

    expect($request->attributes->getBoolean(MarkResponseCacheHit::ATTRIBUTE))->toBeTrue();
});

/**
 * The logging these tests cover runs in an app()->terminating() callback, and
 * Application::terminate() never clears its callback list. That is harmless in
 * production (the process ends after each request) but means a test issuing two
 * HTTP calls replays the first call's callback on the second terminate. So the
 * middleware is driven directly here, one request per test, to keep the write
 * attributable to the request under test.
 */
function dispatchThroughApiKeyMiddleware(bool $servedFromCache = false, string $uri = '/api/public/blog/posts'): void
{
    $request = Request::create($uri, 'GET');
    $request->headers->set('X-API-Key', 'pk_test_middleware_key');

    if ($servedFromCache) {
        $request->attributes->set(MarkResponseCacheHit::ATTRIBUTE, true);
    }

    app(ValidateApiKey::class)
        ->handle($request, fn () => new JsonResponse(['data' => []]));

    app()->terminate();
}

it('logs a request that did real origin work', function () {
    dispatchThroughApiKeyMiddleware();

    expect(ApiConsumerRequest::where('api_consumer_id', $this->consumer->id)->count())->toBe(1);
});

it('does not log a request served from the response cache', function () {
    // The response cache sits behind this middleware, so before the cache-hit
    // flag existed every hit still wrote a row. That is how the table reached
    // 17M rows in production.
    dispatchThroughApiKeyMiddleware(servedFromCache: true);

    expect(ApiConsumerRequest::count())->toBe(0);
});

it('refreshes last_used_at at most once a minute', function () {
    $this->consumer->update(['last_used_at' => null]);

    dispatchThroughApiKeyMiddleware();
    $firstTouch = $this->consumer->fresh()->last_used_at;
    expect($firstTouch)->not->toBeNull();

    $this->travel(30)->seconds();
    dispatchThroughApiKeyMiddleware();
    expect($this->consumer->fresh()->last_used_at->timestamp)->toBe($firstTouch->timestamp);

    $this->travel(2)->minutes();
    dispatchThroughApiKeyMiddleware();
    expect($this->consumer->fresh()->last_used_at->timestamp)->toBeGreaterThan($firstTouch->timestamp);
});

it('prunes logged requests past the retention window', function () {
    ApiConsumerRequest::factory()->create([
        'api_consumer_id' => $this->consumer->id,
        'created_at' => now()->subDays(ApiConsumerRequest::RETENTION_DAYS + 1),
    ]);
    ApiConsumerRequest::factory()->create([
        'api_consumer_id' => $this->consumer->id,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('model:prune', ['--model' => [ApiConsumerRequest::class]])->assertSuccessful();

    expect(ApiConsumerRequest::count())->toBe(1);
});
