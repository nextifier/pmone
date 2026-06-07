<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ResponseCache\Facades\ResponseCache;

uses(RefreshDatabase::class);

beforeEach(function () {
    ResponseCache::clear();

    $this->apiConsumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_programs',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
    ]);

    $this->endpoint = "/api/public/projects/{$this->project->username}/events/{$this->event->slug}/programs";
});

test('returns 401 without api key', function () {
    $this->getJson($this->endpoint)->assertUnauthorized();
});

test('returns active programs resolved to english locale', function () {
    Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Exhibition', 'id' => 'Pameran'],
        'description' => ['en' => 'The main expo', 'id' => 'Pameran utama'],
        'icon' => 'hugeicons:mic-01',
        'order_column' => 1,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_programs'])
        ->getJson($this->endpoint.'?locale=en');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Exhibition')
        ->assertJsonPath('data.0.description', 'The main expo')
        ->assertJsonPath('data.0.iconName', 'hugeicons:mic-01')
        ->assertJsonPath('data.0.image', null);
});

test('returns indonesian translations when locale=id', function () {
    Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Exhibition', 'id' => 'Pameran'],
        'order_column' => 1,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_programs'])
        ->getJson($this->endpoint.'?locale=id');

    $response->assertOk()->assertJsonPath('data.0.title', 'Pameran');
});

test('falls back to english for an unseeded locale', function () {
    Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Exhibition', 'id' => 'Pameran'],
        'order_column' => 1,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_programs'])
        ->getJson($this->endpoint.'?locale=ja');

    $response->assertOk()->assertJsonPath('data.0.title', 'Exhibition');
});

test('hides inactive programs from public', function () {
    Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Active', 'id' => 'Aktif'],
        'is_active' => true,
        'order_column' => 1,
    ]);
    Program::factory()->inactive()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Hidden', 'id' => 'Tersembunyi'],
        'order_column' => 2,
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_programs'])
        ->getJson($this->endpoint.'?locale=en');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Active');
});

test('returns programs ordered by order_column', function () {
    $second = Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Second', 'id' => 'Kedua'],
    ]);
    $first = Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'First', 'id' => 'Pertama'],
    ]);

    $first->order_column = 1;
    $first->save();
    $second->order_column = 2;
    $second->save();

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_programs'])
        ->getJson($this->endpoint.'?locale=en');

    $response->assertOk()
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.1.title', 'Second');
});

test('falls back to a previous event with programs when the event has none', function () {
    // current event ($this->event) has no programs
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2025-01-01 10:00:00',
    ]);
    Program::factory()->create([
        'event_id' => $older->id,
        'title' => ['en' => 'Old Program', 'id' => 'Program Lama'],
        'order_column' => 1,
    ]);

    $this->withHeaders(['X-API-Key' => 'pk_test_programs'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Old Program');
});

test('does not fall back when the event has its own programs', function () {
    Program::factory()->create([
        'event_id' => $this->event->id,
        'title' => ['en' => 'Own Program', 'id' => 'x'],
        'order_column' => 1,
    ]);
    $older = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'start_date' => '2025-01-01 10:00:00',
    ]);
    Program::factory()->create(['event_id' => $older->id, 'order_column' => 1]);

    $this->withHeaders(['X-API-Key' => 'pk_test_programs'])
        ->getJson($this->endpoint.'?locale=en')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Own Program');
});
