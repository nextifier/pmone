<?php

use App\Http\Resources\BrandEventIndexResource;
use App\Http\Resources\PublicBrandIndexResource;
use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes score, is_complete and score_breakdown on the admin resource', function () {
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
    ])->fresh(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media']);

    $array = (new BrandEventIndexResource($brandEvent))->resolve();

    expect($array)->toHaveKeys(['score', 'is_complete', 'score_breakdown']);
    expect($array['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    expect($array['score_breakdown'])->toBeArray()->toHaveCount(10);
});

it('exposes score on the public resource but not is_complete', function () {
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
    ])->fresh(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media']);

    $array = (new PublicBrandIndexResource($brandEvent))->resolve();

    expect($array)->toHaveKeys(['score', 'score_breakdown']);
    expect($array)->not->toHaveKey('is_complete');
    expect($array['score'])->toBeInt();
});

it('returns a score for each row of the public brands endpoint', function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_score',
        'is_active' => true,
    ]);

    $project = Project::factory()->create();
    $event = Event::factory()->published()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);

    $brand = Brand::factory()->public()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_score'])
        ->getJson("/api/public/projects/{$project->username}/brands");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['score', 'score_breakdown'],
            ],
        ]);

    expect($response->json('data.0.score'))->toBeInt();
});
