<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\RundownItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    $this->apiConsumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_rundown',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2026-07-22 09:00:00',
        'end_date' => '2026-07-23 18:00:00',
    ]);

    $this->endpoint = "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/rundown";
});

test('returns 401 without api key', function () {
    $response = $this->getJson($this->endpoint);

    $response->assertUnauthorized();
});

test('returns rundown items grouped by date for english locale', function () {
    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Opening Keynote', 'id' => 'Keynote Pembukaan'],
        'is_active' => true,
    ]);
    RundownItem::factory()->onDate('2026-07-23')->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Closing Session', 'id' => 'Sesi Penutup'],
        'is_active' => true,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint.'?locale=en');

    $response->assertOk()
        ->assertJsonCount(2, 'data.days')
        ->assertJsonPath('data.days.0.day_number', 1)
        ->assertJsonPath('data.days.0.items.0.title', 'Opening Keynote')
        ->assertJsonPath('data.days.1.day_number', 2)
        ->assertJsonPath('data.days.1.items.0.title', 'Closing Session');
});

test('returns indonesian translations when locale=id', function () {
    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Opening Keynote', 'id' => 'Keynote Pembukaan'],
        'is_active' => true,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint.'?locale=id');

    $response->assertOk()
        ->assertJsonPath('data.days.0.items.0.title', 'Keynote Pembukaan');
});

test('hides inactive rundown items from public', function () {
    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $this->event->id,
        'is_active' => true,
        'title' => ['en' => 'Visible'],
    ]);
    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $this->event->id,
        'is_active' => false,
        'title' => ['en' => 'Hidden'],
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint);

    $titles = collect($response->json('data.days'))
        ->flatMap(fn ($d) => collect($d['items'])->pluck('title'))
        ->all();

    expect($titles)->toContain('Visible')->not->toContain('Hidden');
});

test('returns all event days even when no items exist', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint);

    $response->assertOk()
        ->assertJsonCount(2, 'data.days')
        ->assertJsonPath('data.days.0.items', [])
        ->assertJsonPath('data.days.1.items', []);
});

test('returns 404 when event slug does not exist', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson("/api/public/projects/{$this->project->username}/events/nonexistent/rundown");

    $response->assertNotFound();
});

test('public payload omits type and avatar_url', function () {
    RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $this->event->id,
        'is_active' => true,
        'title' => ['en' => 'Item'],
        'speakers' => [
            ['name' => 'Speaker', 'avatar_url' => 'https://example.com/a.jpg'],
        ],
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint);

    $response->assertOk();
    $item = $response->json('data.days.0.items.0');
    expect($item)->not->toHaveKey('type');
    expect($item['speakers'][0])->not->toHaveKey('avatar_url');
});

test('public response exposes website settings', function () {
    $this->project->settings = [
        'website_settings' => [
            'rundown' => [
                'show_search_bar' => false,
                'show_all_rundown_details' => true,
            ],
        ],
    ];
    $this->project->save();

    ResponseCache::clear();

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint);

    $response->assertOk()
        ->assertJsonPath('data.settings.show_search_bar', false)
        ->assertJsonPath('data.settings.show_all_rundown_details', true);
});

test('cache invalidates on rundown item save', function () {
    $item = RundownItem::factory()->onDate('2026-07-22')->create([
        'event_id' => $this->event->id,
        'is_active' => true,
        'title' => ['en' => 'Original'],
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint)
        ->assertJsonPath('data.days.0.items.0.title', 'Original');

    $item->setTranslation('title', 'en', 'Updated')->save();

    $this->withHeaders(['X-API-Key' => 'pk_test_rundown'])
        ->getJson($this->endpoint)
        ->assertJsonPath('data.days.0.items.0.title', 'Updated');
});
