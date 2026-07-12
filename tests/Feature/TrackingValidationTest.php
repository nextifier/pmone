<?php

use App\Models\Click;
use App\Models\Project;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

$ua = ['User-Agent' => 'Mozilla/5.0 (TrackingTest)'];

it('records a click for an existing trackable target', function () use ($ua) {
    $project = Project::factory()->create();

    $this->withHeaders($ua)
        ->postJson('/api/track/click', [
            'clickable_type' => Project::class,
            'clickable_id' => $project->id,
        ])
        ->assertStatus(201);

    expect(Click::where('clickable_type', Project::class)->where('clickable_id', $project->id)->count())->toBe(1);
});

it('rejects a click for a nonexistent trackable id', function () use ($ua) {
    $this->withHeaders($ua)
        ->postJson('/api/track/click', [
            'clickable_type' => Project::class,
            'clickable_id' => 999999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('clickable_id');

    expect(Click::count())->toBe(0);
});

it('records a visit for an existing trackable target', function () use ($ua) {
    $project = Project::factory()->create();

    $this->withHeaders($ua)
        ->postJson('/api/track/visit', [
            'visitable_type' => Project::class,
            'visitable_id' => $project->id,
        ])
        ->assertStatus(201);

    expect(Visit::where('visitable_type', Project::class)->where('visitable_id', $project->id)->count())->toBe(1);
});

it('rejects a visit for a nonexistent trackable id', function () use ($ua) {
    $this->withHeaders($ua)
        ->postJson('/api/track/visit', [
            'visitable_type' => Project::class,
            'visitable_id' => 999999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('visitable_id');

    expect(Visit::count())->toBe(0);
});

it('stores an over-long referer truncated to the max length', function () use ($ua) {
    $project = Project::factory()->create();
    $longReferer = 'https://example.com/'.str_repeat('a', 4000);

    $this->withHeaders($ua + ['referer' => $longReferer])
        ->postJson('/api/track/click', [
            'clickable_type' => Project::class,
            'clickable_id' => $project->id,
        ])
        ->assertStatus(201);

    $click = Click::firstOrFail();
    expect(mb_strlen((string) $click->referer))->toBe(2048)
        ->and($click->referer)->toBe(mb_substr($longReferer, 0, 2048));
});
