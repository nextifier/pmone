<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\ExchangeRate;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
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

test('index exposes event venue, hall, poster and hotel transfer options', function () {
    $event = Event::factory()->create([
        'project_id' => $this->projectA->id,
        'is_active' => true,
        'location' => 'JIExpo Kemayoran',
        'location_link' => 'https://maps.google.com/?q=JIExpo+Kemayoran',
        'hall' => 'Hall A',
    ]);
    $hotel = Hotel::factory()->withEvent($event)->create(['name' => 'Hotel Venue']);
    HotelTransferOption::factory()->for($hotel)->create(['is_active' => true]);

    $response = $this->getJson("/api/public/hotels?event_slug={$event->slug}", $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.event.location', 'JIExpo Kemayoran')
        ->assertJsonPath('data.0.event.location_link', 'https://maps.google.com/?q=JIExpo+Kemayoran')
        ->assertJsonPath('data.0.event.hall', 'Hall A')
        ->assertJsonCount(1, 'data.0.transfer_options')
        ->assertJsonStructure([
            'data' => [['transfer_options', 'event' => ['location', 'location_link', 'hall', 'poster']]],
        ]);
});

test('index attaches estimated_price when the project enabled the foreign-currency estimate', function () {
    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['USD' => 1, 'IDR' => 16000],
        'api_updated_at' => now(),
        'fetched_at' => now(),
    ]);

    $this->projectB->update([
        'settings' => [
            'website_settings' => [
                'hotels' => [
                    'show_estimated_price_in_foreign_currency' => true,
                    'estimated_price_currency' => 'USD',
                ],
            ],
        ],
    ]);

    $response = $this->getJson('/api/public/hotels?project_slug=askindo', $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.0.estimated_price.currency_code', 'USD')
        ->assertJsonPath('data.0.estimated_price.is_stale', false);

    expect($response->json('data.0.estimated_price.rate_per_idr'))
        ->toEqualWithDelta(1 / 16000, 1e-9);
});

test('index omits estimated_price when the toggle is off', function () {
    ExchangeRate::create([
        'base_currency' => 'USD',
        'rates' => ['USD' => 1, 'IDR' => 16000],
        'api_updated_at' => now(),
        'fetched_at' => now(),
    ]);

    $response = $this->getJson('/api/public/hotels?project_slug=askindo', $this->headers);

    $response->assertSuccessful();
    expect($response->json('data.0.estimated_price'))->toBeNull();
});

test('index estimated_price is null when no exchange rate is available', function () {
    $this->projectB->update([
        'settings' => [
            'website_settings' => [
                'hotels' => [
                    'show_estimated_price_in_foreign_currency' => true,
                    'estimated_price_currency' => 'USD',
                ],
            ],
        ],
    ]);

    $response = $this->getJson('/api/public/hotels?project_slug=askindo', $this->headers);

    $response->assertSuccessful();
    expect($response->json('data.0.estimated_price'))->toBeNull();
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
