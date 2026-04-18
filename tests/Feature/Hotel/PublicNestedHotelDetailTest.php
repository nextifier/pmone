<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->consumer = ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);
    $this->headers = ['X-API-Key' => $this->consumer->api_key];
});

test('nested public detail resolves hotel by event+hotel slug', function () {
    $event = Event::factory()->create(['slug' => 'iicc-2026', 'is_active' => true]);
    $hotel = Hotel::factory()->for($event)->create(['slug' => 'grand-mercure']);

    $response = $this->getJson('/api/public/events/iicc-2026/hotels/grand-mercure', $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.slug', 'grand-mercure')
        ->assertJsonPath('data.event.slug', 'iicc-2026');
});

test('nested public detail returns 404 when hotel slug mismatches event', function () {
    $eventA = Event::factory()->create(['slug' => 'event-a', 'is_active' => true]);
    $eventB = Event::factory()->create(['slug' => 'event-b', 'is_active' => true]);
    Hotel::factory()->for($eventA)->create(['slug' => 'same-hotel']);

    $response = $this->getJson('/api/public/events/event-b/hotels/same-hotel', $this->headers);

    $response->assertNotFound();
});

test('nested public detail returns hotel event + project relation', function () {
    $event = Event::factory()->create(['slug' => 'flei-2026', 'is_active' => true]);
    $hotel = Hotel::factory()->for($event)->create(['slug' => 'holiday-inn']);

    $response = $this->getJson('/api/public/events/flei-2026/hotels/holiday-inn', $this->headers);

    $response->assertJsonStructure([
        'data' => [
            'id', 'slug', 'name',
            'event' => ['id', 'slug', 'title', 'is_active', 'project' => ['username', 'name']],
        ],
    ]);
});
