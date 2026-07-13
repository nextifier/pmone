<?php

/**
 * The exception handler in bootstrap/app.php decorates API error responses
 * with CORS headers. It must read the allowlist from config (env-cache safe),
 * never from env() at request time. Two origins are configured because the
 * CORS layer echoes a single allowed origin unconditionally.
 */
beforeEach(function () {
    config(['cors.allowed_origins' => ['https://brand-a.test', 'https://brand-b.test']]);
});

it('adds CORS headers from config to API error responses', function () {
    $response = $this->getJson('/api/definitely-missing-route', ['Origin' => 'https://brand-b.test']);

    $response->assertNotFound();

    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://brand-b.test')
        ->and($response->headers->get('Access-Control-Allow-Credentials'))->toBe('true');
});

it('omits CORS headers for origins outside the configured allowlist', function () {
    $response = $this->getJson('/api/definitely-missing-route', ['Origin' => 'https://unknown-origin.test']);

    $response->assertNotFound();

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});
