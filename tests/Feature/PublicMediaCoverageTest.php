<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\MediaCoverage;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    $this->apiConsumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_media',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
    ]);

    $this->endpoint = "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/media-coverages";
});

test('returns 401 without api key', function () {
    $this->getJson($this->endpoint)->assertUnauthorized();
});

test('returns active media coverages shaped as {title, link, created_at}', function () {
    MediaCoverage::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Megabuild di antaranews',
        'url' => 'https://www.antaranews.com/berita/123/megabuild',
        'published_at' => '2025-12-14T10:00:00',
        'order_column' => 1,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_media'])->getJson($this->endpoint);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Megabuild di antaranews')
        ->assertJsonPath('data.0.link', 'https://www.antaranews.com/berita/123/megabuild')
        ->assertJsonStructure(['data' => [['title', 'link', 'created_at']]]);
});

test('hides inactive media coverages from public', function () {
    MediaCoverage::factory()->create(['event_id' => $this->event->id, 'title' => 'Active', 'is_active' => true, 'order_column' => 1]);
    MediaCoverage::factory()->inactive()->create(['event_id' => $this->event->id, 'title' => 'Hidden', 'order_column' => 2]);

    $this->withHeaders(['X-API-Key' => 'pk_test_media'])->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Active');
});

test('returns media coverages ordered by order_column', function () {
    $second = MediaCoverage::factory()->create(['event_id' => $this->event->id, 'title' => 'Second']);
    $first = MediaCoverage::factory()->create(['event_id' => $this->event->id, 'title' => 'First']);

    $first->order_column = 1;
    $first->save();
    $second->order_column = 2;
    $second->save();

    $this->withHeaders(['X-API-Key' => 'pk_test_media'])->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.1.title', 'Second');
});

test('falls back to a previous event with media coverage when the event has none', function () {
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2025-01-01 10:00:00',
    ]);
    MediaCoverage::factory()->create(['event_id' => $older->id, 'title' => 'Old Coverage', 'order_column' => 1]);

    $this->withHeaders(['X-API-Key' => 'pk_test_media'])->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Old Coverage');
});

test('does not fall back when the event has its own media coverage', function () {
    MediaCoverage::factory()->create(['event_id' => $this->event->id, 'title' => 'Own Coverage', 'order_column' => 1]);
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2025-01-01 10:00:00',
    ]);
    MediaCoverage::factory()->create(['event_id' => $older->id, 'order_column' => 1]);

    $this->withHeaders(['X-API-Key' => 'pk_test_media'])->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Own Coverage');
});
