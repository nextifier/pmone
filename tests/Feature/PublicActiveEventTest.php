<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create([
        'api_key' => 'pk_test_active_event',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
    $this->project->links()->create(['label' => 'Website', 'url' => 'https://example.test']);
});

function getActiveEvent(string $username): TestResponse
{
    return test()->withHeaders(['X-API-Key' => 'pk_test_active_event'])
        ->getJson("/api/public/projects/{$username}/events/active");
}

test('active event exposes display fields from custom_fields and the website link', function () {
    Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
        'location' => 'Some Convention Center, Jakarta',
        'hall' => 'Hall 1',
        'custom_fields' => ['location_short' => 'SCC Jakarta', 'teaser_video_id' => 'abc123'],
    ]);

    getActiveEvent($this->project->username)
        ->assertOk()
        ->assertJsonPath('data.location_short', 'SCC Jakarta')
        ->assertJsonPath('data.teaser_video_id', 'abc123')
        ->assertJsonPath('data.hall', 'Hall 1')
        ->assertJsonPath('data.website_url', 'https://example.test')
        ->assertJsonPath('data.profile_image', null);
});

test('active event exposes conjunction events with sibling project identity', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $sibling = Project::factory()->create(['name' => 'Sibling Expo']);
    $sibling->links()->create(['label' => 'Website', 'url' => 'https://sibling.test']);
    $siblingEvent = Event::factory()->published()->create([
        'project_id' => $sibling->id,
        'title' => 'Sibling Expo 2026',
        'edition_number' => 5,
    ]);

    $event->conjunctionEvents()->attach($siblingEvent->id, ['order_column' => 1]);

    getActiveEvent($this->project->username)
        ->assertOk()
        ->assertJsonCount(1, 'data.conjunction_events')
        ->assertJsonPath('data.conjunction_events.0.name', 'Sibling Expo')
        ->assertJsonPath('data.conjunction_events.0.username', $sibling->username)
        ->assertJsonPath('data.conjunction_events.0.title', 'Sibling Expo 2026')
        ->assertJsonPath('data.conjunction_events.0.edition_number_with_ordinal', '5th')
        ->assertJsonPath('data.conjunction_events.0.website_url', 'https://sibling.test');
});

test('active event returns 404 when no active published event exists', function () {
    Event::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => false,
    ]);

    getActiveEvent($this->project->username)->assertNotFound();
});
