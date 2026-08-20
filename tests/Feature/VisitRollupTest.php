<?php

use App\Models\Click;
use App\Models\DailyClickStat;
use App\Models\DailyVisitStat;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Post;
use App\Models\ProjectBanner;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Visit;
use App\Support\VisitStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->post = Post::factory()->create([
        'status' => 'published',
        'published_at' => now()->subYear(),
    ]);

    // Both discontinuity dates are in the past for most tests here, so the rollup
    // behaves the way it will once the event websites are rebuilt.
    config()->set('visit-tracking.browser_counting_since', '2020-01-01');
    config()->set('visit-tracking.visitor_ip_tracking_since', '2020-01-01');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function rollupVisit(Post $post, string $date, array $attributes = []): Visit
{
    return Visit::factory()->create(array_merge([
        'visitable_type' => Post::class,
        'visitable_id' => $post->id,
        'visited_at' => $date.' 09:00:00',
    ], $attributes));
}

test('it summarises a day into one row per source', function () {
    $day = now()->subDays(2)->toDateString();

    // The old server-side path never carried a browser User-Agent.
    rollupVisit($this->post, $day, ['user_agent' => null]);
    rollupVisit($this->post, $day, ['user_agent' => null]);
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    expect(DailyVisitStat::count())->toBe(2);

    $serverRender = DailyVisitStat::where('source', VisitStats::SOURCE_SERVER_RENDER)->sole();
    $beacon = DailyVisitStat::where('source', VisitStats::SOURCE_BEACON)->sole();

    expect($serverRender->views)->toBe(2)
        ->and($beacon->views)->toBe(1)
        ->and($beacon->visitable_id)->toBe($this->post->id);
});

test('it produces identical numbers when a day is rolled up twice', function () {
    $day = now()->subDays(2)->toDateString();

    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);
    rollupVisit($this->post, $day, ['user_agent' => 'Chrome']);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();
    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    // Appending instead of updating in place is the failure this guards: a nightly
    // job that double-counts every time it retries.
    expect(DailyVisitStat::count())->toBe(1)
        ->and(DailyVisitStat::sole()->views)->toBe(2);
});

test('it counts a distinct visitor per ip and user agent pair', function () {
    $day = now()->subDays(2)->toDateString();

    rollupVisit($this->post, $day, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);
    rollupVisit($this->post, $day, ['ip_address' => '10.0.0.1', 'user_agent' => 'Firefox']);
    rollupVisit($this->post, $day, ['ip_address' => '10.0.0.1', 'user_agent' => 'Safari']);
    rollupVisit($this->post, $day, ['ip_address' => '10.0.0.2', 'user_agent' => 'Firefox']);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    $stat = DailyVisitStat::where('source', VisitStats::SOURCE_BEACON)->sole();

    expect($stat->views)->toBe(4)->and($stat->unique_visitors)->toBe(3);
});

test('it leaves unique visitors null for days before the visitor ip date', function () {
    $day = now()->subDays(2)->toDateString();
    config()->set('visit-tracking.visitor_ip_tracking_since', now()->toDateString());

    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    // Null means "not measurable", not zero: before the event websites forwarded
    // the visitor IP every beacon shared one Cloudflare Worker address.
    expect(DailyVisitStat::where('source', VisitStats::SOURCE_BEACON)->sole()->unique_visitors)
        ->toBeNull();
});

test('it rolls up every visitable type, not just posts', function () {
    $day = now()->subDays(2)->toDateString();
    $banner = ProjectBanner::factory()->create();

    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);
    Visit::factory()->create([
        'visitable_type' => ProjectBanner::class,
        'visitable_id' => $banner->id,
        'visited_at' => $day.' 09:00:00',
        'user_agent' => 'Firefox',
    ]);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    expect(DailyVisitStat::where('visitable_type', ProjectBanner::class)->sole()->views)->toBe(1);
});

test('it summarises clicks without a source dimension', function () {
    $day = now()->subDays(2)->toDateString();
    $banner = ProjectBanner::factory()->create();

    Click::factory()->count(3)->create([
        'clickable_type' => ProjectBanner::class,
        'clickable_id' => $banner->id,
        'clicked_at' => $day.' 09:00:00',
    ]);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    expect(DailyClickStat::sole()->clicks)->toBe(3);
});

test('lifetime views never include the server render era', function () {
    $day = now()->subDays(2)->toDateString();

    rollupVisit($this->post, $day, ['user_agent' => null]);
    rollupVisit($this->post, $day, ['user_agent' => null]);
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    // Those two server-render rows counted crawlers and prefetchers; the whole
    // point of keeping the source on the row is that they never get added in.
    expect($this->post->fresh()->lifetime_views)->toBe(1);
});

test('lifetime views prefer ga4 before the cutover and the beacon after it', function () {
    config()->set('visit-tracking.browser_counting_since', '2026-07-28');

    DailyVisitStat::query()->create([
        'visitable_type' => Post::class,
        'visitable_id' => $this->post->id,
        'date' => '2026-07-01',
        'source' => VisitStats::SOURCE_GA4,
        'views' => 100,
    ]);
    // Same day, the inflated in-house number. Must be ignored, not added.
    DailyVisitStat::query()->create([
        'visitable_type' => Post::class,
        'visitable_id' => $this->post->id,
        'date' => '2026-07-01',
        'source' => VisitStats::SOURCE_SERVER_RENDER,
        'views' => 500,
    ]);
    DailyVisitStat::query()->create([
        'visitable_type' => Post::class,
        'visitable_id' => $this->post->id,
        'date' => '2026-08-01',
        'source' => VisitStats::SOURCE_BEACON,
        'views' => 7,
    ]);
    // GA4 also covers a date the beacon owns. The beacon wins there.
    DailyVisitStat::query()->create([
        'visitable_type' => Post::class,
        'visitable_id' => $this->post->id,
        'date' => '2026-08-01',
        'source' => VisitStats::SOURCE_GA4,
        'views' => 6,
    ]);

    $this->artisan('visits:rollup', ['--date' => now()->subDays(2)->toDateString()])
        ->assertSuccessful();

    expect($this->post->fresh()->lifetime_views)->toBe(107);
});

test('lifetime views heal themselves when the cached number drifts', function () {
    $day = now()->subDays(2)->toDateString();
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();
    expect($this->post->fresh()->lifetime_views)->toBe(1);

    Post::query()->toBase()->where('id', $this->post->id)->update(['lifetime_views' => 999]);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    expect($this->post->fresh()->lifetime_views)->toBe(1);
});

test('recomputing lifetime views does not touch the post timestamp', function () {
    $day = now()->subDays(2)->toDateString();
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);

    $before = $this->post->fresh()->updated_at;

    $this->travel(1)->days();
    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    // A moved updated_at would mean the write went through the model, which fires
    // ClearsResponseCache: a nightly purge of every article across 16 event sites,
    // plus a sitemap lastmod that changes every day for no reason.
    expect($this->post->fresh()->updated_at->eq($before))->toBeTrue()
        ->and($this->post->fresh()->lifetime_views)->toBe(1);
});

test('a dry run reports without writing', function () {
    $day = now()->subDays(2)->toDateString();
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);

    $this->artisan('visits:rollup', ['--date' => $day, '--dry-run' => true])->assertSuccessful();

    expect(DailyVisitStat::count())->toBe(0)
        ->and($this->post->fresh()->lifetime_views)->toBe(0);
});

test('views survive after the raw rows are pruned', function () {
    // The whole point. This day is far outside the 90-day retention window, so
    // there is no row in `visits` at all — only the rollup knows it happened.
    $longAgo = now()->subDays(200)->toDateString();

    DailyVisitStat::query()->create([
        'visitable_type' => Post::class,
        'visitable_id' => $this->post->id,
        'date' => $longAgo,
        'source' => VisitStats::SOURCE_BEACON,
        'views' => 4200,
        'authenticated_views' => 0,
    ]);

    $this->artisan('visits:rollup', ['--date' => now()->subDay()->toDateString()])
        ->assertSuccessful();

    expect(Visit::count())->toBe(0)
        ->and($this->post->fresh()->lifetime_views)->toBe(4200);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson(
        '/api/posts/analytics?start_date='.$longAgo.'&end_date='.now()->toDateString()
    );

    $response->assertSuccessful();
    expect($response->json('data.summary.total_visits'))->toBe(4200);

    $day = collect($response->json('data.visits_per_day'))->firstWhere('date', $longAgo);
    expect($day['count'])->toBe(4200);
});

test('re-rolling a day whose raw rows are gone leaves the summary alone', function () {
    $day = now()->subDays(2)->toDateString();
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();
    expect($this->post->fresh()->lifetime_views)->toBe(2);

    // Once tracking:cleanup has pruned the raw rows, the rollup is the only record.
    // Running the command over that date again must be a no-op, not a reset to zero
    // — otherwise a stray backfill invocation would erase years of history.
    Visit::query()->delete();

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    expect(DailyVisitStat::sole()->views)->toBe(2)
        ->and($this->post->fresh()->lifetime_views)->toBe(2);
});

test('a trashed post still gets its lifetime total', function () {
    $day = now()->subDays(2)->toDateString();
    rollupVisit($this->post, $day, ['user_agent' => 'Firefox']);
    rollupVisit($this->post, $day, ['user_agent' => 'Chrome']);

    $this->post->delete();

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    // The trash listing shows this column, and a restored post that reads zero
    // would look like its history had been thrown away.
    expect(Post::withTrashed()->find($this->post->id)->lifetime_views)->toBe(2);
});

test('link pages, brands and banners get lifetime totals too', function () {
    $day = now()->subDays(2)->toDateString();

    $linkPage = LinkPage::factory()->create();
    $item = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id]);
    $banner = ProjectBanner::factory()->create();

    Visit::factory()->count(3)->create([
        'visitable_type' => LinkPage::class,
        'visitable_id' => $linkPage->id,
        'visited_at' => $day.' 09:00:00',
        'user_agent' => 'Firefox',
    ]);
    Visit::factory()->count(5)->create([
        'visitable_type' => ProjectBanner::class,
        'visitable_id' => $banner->id,
        'visited_at' => $day.' 09:00:00',
        'user_agent' => 'Firefox',
    ]);
    Click::factory()->count(2)->create([
        'clickable_type' => ProjectBanner::class,
        'clickable_id' => $banner->id,
        'clicked_at' => $day.' 09:00:00',
    ]);
    // Clicks land on the item, never on the page itself.
    Click::factory()->count(7)->create([
        'clickable_type' => LinkPageItem::class,
        'clickable_id' => $item->id,
        'clicked_at' => $day.' 09:00:00',
    ]);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    expect($linkPage->fresh()->lifetime_views)->toBe(3)
        ->and($linkPage->fresh()->lifetime_clicks)->toBe(7)
        ->and($banner->fresh()->lifetime_impressions)->toBe(5)
        ->and($banner->fresh()->lifetime_clicks)->toBe(2);
});

test('types without a google analytics history count the beacon on every date', function () {
    // The era rule exists because article counting broke for two months. Nothing
    // else was ever counted server-side, so applying that rule to a link page would
    // silently drop everything it had before 28 July.
    config()->set('visit-tracking.browser_counting_since', '2026-07-28');

    $linkPage = LinkPage::factory()->create();

    DailyVisitStat::query()->create([
        'visitable_type' => LinkPage::class,
        'visitable_id' => $linkPage->id,
        'date' => '2026-06-01',
        'source' => VisitStats::SOURCE_BEACON,
        'views' => 40,
    ]);
    DailyVisitStat::query()->create([
        'visitable_type' => LinkPage::class,
        'visitable_id' => $linkPage->id,
        'date' => '2026-08-01',
        'source' => VisitStats::SOURCE_BEACON,
        'views' => 2,
    ]);

    $this->artisan('visits:rollup', ['--date' => now()->subDays(2)->toDateString()])
        ->assertSuccessful();

    expect($linkPage->fresh()->lifetime_views)->toBe(42);
});

test('banner analytics reads history the raw table no longer holds', function () {
    // A campaign sold on three months: the raw rows for its first weeks are long
    // gone, and only the rollup remembers them.
    $banner = ProjectBanner::factory()->create();
    $longAgo = now()->subDays(200)->toDateString();

    DailyVisitStat::query()->create([
        'visitable_type' => ProjectBanner::class,
        'visitable_id' => $banner->id,
        'date' => $longAgo,
        'source' => VisitStats::SOURCE_BEACON,
        'views' => 9000,
        'authenticated_views' => 0,
    ]);
    DailyClickStat::query()->create([
        'clickable_type' => ProjectBanner::class,
        'clickable_id' => $banner->id,
        'date' => $longAgo,
        'clicks' => 90,
    ]);

    $this->artisan('visits:rollup', ['--date' => now()->subDay()->toDateString()])
        ->assertSuccessful();

    expect(Visit::count())->toBe(0)
        ->and($banner->fresh()->lifetime_impressions)->toBe(9000)
        ->and($banner->fresh()->lifetime_clicks)->toBe(90);

    $series = VisitStats::viewSeries(
        ProjectBanner::class,
        now()->subDays(250),
        now(),
        fn ($query) => $query->where('visitable_id', $banner->id),
    );

    expect($series['total'])->toBe(9000)
        ->and($series['per_day'][$longAgo]['views'])->toBe(9000);
});

test('short links and link page items get lifetime click totals', function () {
    $day = now()->subDays(2)->toDateString();

    $shortLink = ShortLink::factory()->create();
    $linkPage = LinkPage::factory()->create();
    $item = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id]);

    Click::factory()->count(9)->create([
        'clickable_type' => ShortLink::class,
        'clickable_id' => $shortLink->id,
        'clicked_at' => $day.' 09:00:00',
    ]);
    Click::factory()->count(4)->create([
        'clickable_type' => LinkPageItem::class,
        'clickable_id' => $item->id,
        'clicked_at' => $day.' 09:00:00',
    ]);

    $this->artisan('visits:rollup', ['--date' => $day])->assertSuccessful();

    expect($shortLink->fresh()->lifetime_clicks)->toBe(9)
        ->and($item->fresh()->lifetime_clicks)->toBe(4)
        // The page total still comes from its items, not from the page itself.
        ->and($linkPage->fresh()->lifetime_clicks)->toBe(4);
});

test('short link analytics reads history the raw table no longer holds', function () {
    $shortLink = ShortLink::factory()->create();
    $longAgo = now()->subDays(200)->toDateString();

    DailyClickStat::query()->create([
        'clickable_type' => ShortLink::class,
        'clickable_id' => $shortLink->id,
        'date' => $longAgo,
        'clicks' => 1200,
    ]);

    $this->artisan('visits:rollup', ['--date' => now()->subDay()->toDateString()])
        ->assertSuccessful();

    expect(Click::count())->toBe(0)
        ->and($shortLink->fresh()->lifetime_clicks)->toBe(1200);

    $series = VisitStats::clickSeries(
        ShortLink::class,
        now()->subDays(250),
        now(),
        fn ($query) => $query->where('clickable_id', $shortLink->id),
    );

    expect(array_sum($series))->toBe(1200)->and($series[$longAgo])->toBe(1200);
});
