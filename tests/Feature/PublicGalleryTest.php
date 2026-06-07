<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    ResponseCache::clear();

    ApiConsumer::factory()->create(['api_key' => 'pk_test_gallery', 'is_active' => true]);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-06-04 10:00:00',
    ]);

    $this->endpoint = "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/gallery";

    $this->addPhoto = function (Event $event, string $name = 'photo.jpg') {
        $event->addMedia(UploadedFile::fake()->image($name, 800, 600))->toMediaCollection('gallery');
    };
});

test('returns 401 without api key', function () {
    $this->getJson($this->endpoint)->assertUnauthorized();
});

test('returns the event gallery photos', function () {
    ($this->addPhoto)($this->event, 'a.jpg');
    ($this->addPhoto)($this->event, 'b.jpg');

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_gallery'])->getJson($this->endpoint);

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0'))->toHaveKeys(['id', 'lqip', 'sm', 'xl', 'url', 'width', 'height']);
    expect($response->json('data.0.url'))->not->toBeNull();
});

test('falls back to a previous event with gallery when the event has none', function () {
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2025-01-01 10:00:00',
    ]);
    ($this->addPhoto)($older, 'old.jpg');
    // $this->event has no gallery

    $this->withHeaders(['X-API-Key' => 'pk_test_gallery'])
        ->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('does not fall back when the event has its own gallery', function () {
    ($this->addPhoto)($this->event, 'own.jpg');

    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2025-01-01 10:00:00',
    ]);
    ($this->addPhoto)($older, 'o1.jpg');
    ($this->addPhoto)($older, 'o2.jpg');

    $this->withHeaders(['X-API-Key' => 'pk_test_gallery'])
        ->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('returns empty when no event in the project has a gallery', function () {
    $this->withHeaders(['X-API-Key' => 'pk_test_gallery'])
        ->getJson($this->endpoint)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
