<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
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

    $this->projectA = Project::factory()->create(['status' => 'active', 'username' => 'flei']);
    $this->projectB = Project::factory()->create(['status' => 'active', 'username' => 'askindo']);

    $this->eventActiveA = Event::factory()->create(['project_id' => $this->projectA->id, 'is_active' => true]);
    $this->eventInactiveA = Event::factory()->create(['project_id' => $this->projectA->id, 'is_active' => false]);
    $this->eventActiveB = Event::factory()->create(['project_id' => $this->projectB->id, 'is_active' => true]);

    Hotel::factory()->for($this->eventActiveA)->create(['name' => 'Hotel ActiveA']);
    Hotel::factory()->for($this->eventInactiveA)->create(['name' => 'Hotel InactiveA']);
    Hotel::factory()->for($this->eventActiveB)->create(['name' => 'Hotel ActiveB']);
});

test('default lists only hotels from active events', function () {
    $response = $this->getJson('/api/public/hotels', $this->headers);

    $response->assertSuccessful();
    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Hotel ActiveA');
    expect($names)->toContain('Hotel ActiveB');
    expect($names)->not->toContain('Hotel InactiveA');
});

test('include_inactive=true returns hotels from all events', function () {
    $response = $this->getJson('/api/public/hotels?include_inactive=true', $this->headers);

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Hotel InactiveA');
});

test('project_slug filter returns only hotels from that project active event', function () {
    $response = $this->getJson('/api/public/hotels?project_slug=flei', $this->headers);

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Hotel ActiveA');
    expect($names)->not->toContain('Hotel ActiveB');
    expect($names)->not->toContain('Hotel InactiveA');
});

test('event_slug filter returns only hotels from specific event', function () {
    $response = $this->getJson("/api/public/hotels?event_slug={$this->eventActiveA->slug}", $this->headers);

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Hotel ActiveA');
    expect($names)->not->toContain('Hotel ActiveB');
});
