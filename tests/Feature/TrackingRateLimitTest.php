<?php

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('tracking');
    $this->post = Post::factory()->create([
        'status' => 'published',
        'published_at' => now()->subWeek(),
    ]);
});

function trackVisit(string $userAgent): TestResponse
{
    return test()
        ->withHeaders(['User-Agent' => $userAgent])
        ->postJson('/api/track/visit', [
            'visitable_type' => Post::class,
            'visitable_id' => test()->post->id,
        ]);
}

it('throttles a single visitor at the documented ceiling', function () {
    foreach (range(1, 120) as $ignored) {
        trackVisit('Mozilla/5.0 (RateLimitTest Reader)')->assertStatus(201);
    }

    trackVisit('Mozilla/5.0 (RateLimitTest Reader)')->assertStatus(429);
});

it('keeps separate buckets per user agent behind one shared address', function () {
    // Every beacon from the event websites arrives from a small set of
    // Cloudflare Worker egress addresses, and Indonesian carriers put thousands
    // of readers behind one CGNAT address. Keying on the IP alone would let one
    // heavy reader silence everyone sharing it.
    foreach (range(1, 120) as $ignored) {
        trackVisit('Mozilla/5.0 (RateLimitTest Reader)')->assertStatus(201);
    }

    trackVisit('Mozilla/5.0 (RateLimitTest Reader)')->assertStatus(429);
    trackVisit('Mozilla/5.0 (RateLimitTest Someone Else)')->assertStatus(201);
});
