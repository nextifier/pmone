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

it('keeps tracking data inside the retention window and deletes the rest', function () {
    makeVisit(89);
    makeVisit(91);
    makeClick(89);
    makeClick(91);

    $this->artisan('tracking:cleanup')->assertSuccessful();

    expect(Visit::count())->toBe(1)
        ->and(Click::count())->toBe(1);
});

it('honours an explicit retention window', function () {
    makeVisit(5);
    makeVisit(15);

    $this->artisan('tracking:cleanup', ['--days' => 10])->assertSuccessful();

    expect(Visit::count())->toBe(1);
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
    $this->artisan('tracking:cleanup', ['--chunk' => 2])->assertSuccessful();

    expect(Visit::count())->toBe(2);
});
