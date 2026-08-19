<?php

use App\Models\Brand;
use App\Models\Click;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->brand = Brand::factory()->create();
});

function makeVisit(int $daysAgo): Visit
{
    return Visit::factory()->create([
        'visitable_type' => Brand::class,
        'visitable_id' => test()->brand->id,
        'visited_at' => now()->subDays($daysAgo),
    ]);
}

function makeClick(int $daysAgo): Click
{
    return Click::factory()->create([
        'clickable_type' => Brand::class,
        'clickable_id' => test()->brand->id,
        'clicked_at' => now()->subDays($daysAgo),
    ]);
}

/**
 * Summarise the days a test is about to expire, the way the 01:45 schedule does
 * before the 02:00 purge. Without this the cleanup refuses to run at all.
 */
function rollUpDays(int $fromDaysAgo, int $toDaysAgo): void
{
    test()->artisan('visits:rollup', [
        '--from' => now()->subDays($fromDaysAgo)->toDateString(),
        '--to' => now()->subDays($toDaysAgo)->toDateString(),
    ])->assertSuccessful();
}

it('keeps tracking data inside the retention window and deletes the rest', function () {
    makeVisit(89);
    makeVisit(91);
    makeClick(89);
    makeClick(91);

    rollUpDays(91, 89);

    $this->artisan('tracking:cleanup')->assertSuccessful();

    expect(Visit::count())->toBe(1)
        ->and(Click::count())->toBe(1);
});

it('honours an explicit retention window', function () {
    makeVisit(5);
    makeVisit(15);

    rollUpDays(15, 5);

    $this->artisan('tracking:cleanup', ['--days' => 10])->assertSuccessful();

    expect(Visit::count())->toBe(1);
});

it('refuses to delete a day that was never rolled up', function () {
    makeVisit(91);
    makeVisit(1);

    // The whole point of the interlock: a rollup that failed, or never ran, must
    // stop the purge rather than let the count disappear with nobody noticing.
    $this->artisan('tracking:cleanup')->assertFailed();

    expect(Visit::count())->toBe(2);
});

it('deletes once the missing day has been rolled up', function () {
    makeVisit(91);
    makeVisit(1);

    $this->artisan('tracking:cleanup')->assertFailed();

    rollUpDays(91, 91);

    $this->artisan('tracking:cleanup')->assertSuccessful();

    expect(Visit::count())->toBe(1);
});

it('can be forced past the interlock when the missing days are unrecoverable', function () {
    makeVisit(91);

    $this->artisan('tracking:cleanup', ['--skip-rollup-check' => true])->assertSuccessful();

    expect(Visit::count())->toBe(0);
});

it('deletes every expired row even when they span multiple chunks', function () {
    foreach (range(1, 7) as $ignored) {
        makeVisit(200);
    }
    makeVisit(1);
    makeVisit(2);

    // A chunk size below the number of expired rows forces the delete loop to
    // run more than once. The command previously issued one unbounded DELETE,
    // which on the production visits table would mean millions of rows in a
    // single statement.
    //
    // Skips the interlock rather than rolling up 200 days one at a time: this test
    // is about the delete loop, and the interlock has its own tests above.
    $this->artisan('tracking:cleanup', ['--chunk' => 2, '--skip-rollup-check' => true])
        ->assertSuccessful();

    expect(Visit::count())->toBe(2);
});
