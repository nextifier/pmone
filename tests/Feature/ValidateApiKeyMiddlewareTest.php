<?php

use App\Models\ApiConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
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
