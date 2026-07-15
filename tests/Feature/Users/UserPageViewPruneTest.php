<?php

use App\Models\UserPageView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prunes page views past the retention window', function () {
    $old = UserPageView::factory()->create(['visited_at' => now()->subDays(91)]);
    $recent = UserPageView::factory()->create(['visited_at' => now()->subDay()]);

    $this->artisan('model:prune', ['--model' => [UserPageView::class]])->assertSuccessful();

    expect(UserPageView::query()->whereKey($old->id)->exists())->toBeFalse();
    expect(UserPageView::query()->whereKey($recent->id)->exists())->toBeTrue();
});
