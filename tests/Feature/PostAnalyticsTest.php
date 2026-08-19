<?php

use App\Models\Post;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Unique visitors are suppressed until the event websites forward a real
    // visitor IP. Most tests here assume that day has come; the two that check
    // the suppression itself override this.
    config()->set('visit-tracking.visitor_ip_tracking_since', '2020-01-01');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function visitPost(Post $post, array $attributes = []): Visit
{
    return Visit::factory()->create(array_merge([
        'visitable_type' => Post::class,
        'visitable_id' => $post->id,
        'visited_at' => now()->subDay(),
    ], $attributes));
}

/**
 * View figures are read from the permanent rollup for every completed day, so a
 * test that seeds raw visits has to summarise them the way the nightly schedule
 * does before the endpoint can see them.
 */
function rollUpSeededVisits(): void
{
    test()->artisan('visits:rollup', ['--days' => 7])->assertSuccessful();
}

test('overall analytics counts visits on published posts only', function () {
    $published = Post::factory()->create(['status' => 'published', 'published_at' => now()->subWeek()]);
    $draft = Post::factory()->create(['status' => 'draft', 'published_at' => null]);
    $scheduled = Post::factory()->create(['status' => 'scheduled', 'published_at' => now()->addWeek()]);
    $trashed = Post::factory()->create(['status' => 'published', 'published_at' => now()->subWeek()]);

    visitPost($published);
    visitPost($published);
    visitPost($draft);
    visitPost($scheduled);
    visitPost($trashed);

    $trashed->delete();

    rollUpSeededVisits();

    $response = $this->getJson('/api/posts/analytics?days=30');

    $response->assertSuccessful();
    expect($response->json('data.summary.total_visits'))->toBe(2);
});

test('overall analytics counts a distinct visitor per ip and user agent pair', function () {
    $post = Post::factory()->create(['status' => 'published', 'published_at' => now()->subWeek()]);

    // Same reader refreshing three times.
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);
    // Housemate on the same NAT address, different browser.
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Safari']);
    // Someone else entirely.
    visitPost($post, ['ip_address' => '10.0.0.2', 'user_agent' => 'Firefox']);

    rollUpSeededVisits();

    $response = $this->getJson('/api/posts/analytics?days=30');

    $response->assertSuccessful();
    expect($response->json('data.summary.total_visits'))->toBe(5)
        ->and($response->json('data.summary.unique_visitors'))->toBe(3);
});

test('overall analytics reports unique visitors per day and per top post', function () {
    $post = Post::factory()->create(['status' => 'published', 'published_at' => now()->subWeek()]);

    $yesterday = now()->subDay()->startOfDay()->addHours(9);
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox', 'visited_at' => $yesterday]);
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox', 'visited_at' => $yesterday]);
    visitPost($post, ['ip_address' => '10.0.0.2', 'user_agent' => 'Firefox', 'visited_at' => $yesterday]);

    rollUpSeededVisits();

    $response = $this->getJson('/api/posts/analytics?days=30');

    $response->assertSuccessful();

    $day = collect($response->json('data.visits_per_day'))
        ->firstWhere('date', $yesterday->toDateString());

    expect($day['count'])->toBe(3)
        ->and($day['unique_count'])->toBe(2)
        ->and($response->json('data.top_posts.0.visits_count'))->toBe(3)
        ->and($response->json('data.top_posts.0.unique_visitors_count'))->toBe(2);
});

test('overall analytics exposes the tracking methodology dates', function () {
    config()->set('visit-tracking.browser_counting_since', '2026-07-28');
    config()->set('visit-tracking.visitor_ip_tracking_since', null);

    rollUpSeededVisits();

    $response = $this->getJson('/api/posts/analytics?days=30');

    $response->assertSuccessful();
    expect($response->json('data.meta.browser_counting_since'))->toBe('2026-07-28')
        ->and($response->json('data.meta.unique_visitors_since'))->toBeNull();
});

test('per post analytics reports unique visitors', function () {
    $post = Post::factory()->create(['status' => 'published', 'published_at' => now()->subWeek()]);

    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);
    visitPost($post, ['ip_address' => '10.0.0.2', 'user_agent' => 'Chrome']);

    rollUpSeededVisits();

    $response = $this->getJson("/api/posts/{$post->slug}/analytics?days=30");

    $response->assertSuccessful();
    expect($response->json('data.summary.total_visits'))->toBe(3)
        ->and($response->json('data.summary.unique_visitors'))->toBe(2)
        ->and($response->json('data.meta'))->toHaveKeys(['browser_counting_since', 'unique_visitors_since']);
});

test('reading a post through the public endpoint does not record a visit', function () {
    $post = Post::factory()->create([
        'status' => 'published',
        'published_at' => now()->subWeek(),
        'visibility' => 'public',
    ]);

    // Counting belongs to POST /api/track/visit, which filters bots and is rate
    // limited. This route is public and unauthenticated, so a row written here
    // could be forged in a loop.
    app('auth')->forgetGuards();
    auth()->logout();

    $this->getJson("/api/posts/{$post->slug}")->assertSuccessful();

    expect(Visit::where('visitable_type', Post::class)->count())->toBe(0);
});

test('unique visitors are withheld until the visitor ip date is configured', function () {
    config()->set('visit-tracking.visitor_ip_tracking_since', null);

    $post = Post::factory()->create(['status' => 'published', 'published_at' => now()->subWeek()]);
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);

    rollUpSeededVisits();

    $response = $this->getJson('/api/posts/analytics?days=30');

    $response->assertSuccessful();
    expect($response->json('data.summary.total_visits'))->toBe(1)
        ->and($response->json('data.summary.unique_visitors'))->toBeNull()
        ->and($response->json('data.visits_per_day.0.unique_count'))->toBeNull()
        ->and($response->json('data.top_posts.0.unique_visitors_count'))->toBeNull();
});

test('unique visitors are withheld for a range that starts before the visitor ip date', function () {
    config()->set('visit-tracking.visitor_ip_tracking_since', now()->subDays(5)->toDateString());

    $post = Post::factory()->create(['status' => 'published', 'published_at' => now()->subWeek()]);
    visitPost($post, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);

    rollUpSeededVisits();

    // Range starts 30 days ago, so most of it predates real visitor IPs.
    expect($this->getJson('/api/posts/analytics?days=30')->json('data.summary.unique_visitors'))
        ->toBeNull();

    // Range starts after the cutover, so the figure is trustworthy.
    expect($this->getJson('/api/posts/analytics?days=2')->json('data.summary.unique_visitors'))
        ->toBeInt();
});
