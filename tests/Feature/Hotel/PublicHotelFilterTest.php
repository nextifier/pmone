<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
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

    // Both projects need an active configured gateway for public hotel listing
    // to surface their events; absence triggers the same hidden-feature path
    // as `hotel_reservation_enabled = false`.
    ProjectPaymentGateway::factory()->create(['project_id' => $this->projectA->id, 'is_active' => true]);
    ProjectPaymentGateway::factory()->create(['project_id' => $this->projectB->id, 'is_active' => true]);

    $this->eventActiveA = Event::factory()->create(['project_id' => $this->projectA->id, 'is_active' => true]);
    $this->eventInactiveA = Event::factory()->create(['project_id' => $this->projectA->id, 'is_active' => false]);
    $this->eventActiveB = Event::factory()->create(['project_id' => $this->projectB->id, 'is_active' => true]);

    Hotel::factory()->withEvent($this->eventActiveA)->create(['name' => 'Hotel ActiveA']);
    Hotel::factory()->withEvent($this->eventInactiveA)->create(['name' => 'Hotel InactiveA']);
    Hotel::factory()->withEvent($this->eventActiveB)->create(['name' => 'Hotel ActiveB']);
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

test('hotels hidden when project has no active payment gateway', function () {
    $project = Project::factory()->create(['status' => 'active', 'username' => 'no-gateway']);
    $event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);
    Hotel::factory()->withEvent($event)->create(['name' => 'Hotel NoGateway']);

    $names = collect($this->getJson('/api/public/hotels', $this->headers)->json('data'))
        ->pluck('name')
        ->all();

    expect($names)->not->toContain('Hotel NoGateway');
});

test('hotels hidden when project gateway is inactive', function () {
    $project = Project::factory()->create(['status' => 'active', 'username' => 'inactive-gw']);
    ProjectPaymentGateway::factory()->create(['project_id' => $project->id, 'is_active' => false]);
    $event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);
    Hotel::factory()->withEvent($event)->create(['name' => 'Hotel InactiveGw']);

    $names = collect($this->getJson('/api/public/hotels', $this->headers)->json('data'))
        ->pluck('name')
        ->all();

    expect($names)->not->toContain('Hotel InactiveGw');
});

test('hotels hidden when project gateway secret_key is placeholder', function () {
    $project = Project::factory()->create(['status' => 'active', 'username' => 'placeholder-gw']);
    ProjectPaymentGateway::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
        'secret_key' => 'xnd_dummy_placeholder_replace_me_with_real',
    ]);
    $event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);
    Hotel::factory()->withEvent($event)->create(['name' => 'Hotel PlaceholderGw']);

    $names = collect($this->getJson('/api/public/hotels', $this->headers)->json('data'))
        ->pluck('name')
        ->all();

    expect($names)->not->toContain('Hotel PlaceholderGw');
});
