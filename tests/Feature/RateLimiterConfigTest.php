<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

function resolveLimitFor(string $name, Request $request): Limit
{
    $limiter = RateLimiter::limiter($name);

    expect($limiter)->not->toBeNull();

    return $limiter($request);
}

test('api limiter allows 120 requests per minute', function () {
    $limit = resolveLimitFor('api', Request::create('/api/test', 'GET'));

    expect($limit->maxAttempts)->toBe(120)
        ->and($limit->decaySeconds)->toBe(60);
});

test('short-link limiter allows 300 requests per minute', function () {
    $limit = resolveLimitFor('short-link', Request::create('/api/s/example', 'GET'));

    expect($limit->maxAttempts)->toBe(300)
        ->and($limit->decaySeconds)->toBe(60);
});

test('short-link limiter is keyed by ip for anonymous visitors', function () {
    $request = Request::create('/api/s/example', 'GET', server: ['REMOTE_ADDR' => '203.0.113.7']);

    $limit = resolveLimitFor('short-link', $request);

    expect($limit->key)->toBe('203.0.113.7');
});

test('short link resolution routes use the short-link throttle', function () {
    $resolve = collect(app('router')->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/resolve/{slug}');
    $shortLink = collect(app('router')->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/s/{slug}');

    expect($resolve->gatherMiddleware())->toContain('throttle:short-link')
        ->and($shortLink->gatherMiddleware())->toContain('throttle:short-link');
});
