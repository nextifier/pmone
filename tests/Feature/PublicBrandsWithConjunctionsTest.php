<?php

use App\Models\ApiConsumer;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiConsumer = ApiConsumer::factory()->create([
        'name' => 'Test Website',
        'api_key' => 'pk_test_conjunction',
        'is_active' => true,
    ]);

    $this->project = Project::factory()->create();
});

test('returns empty groups when no active event', function () {
    $response = $this->withHeaders(['X-API-Key' => 'pk_test_conjunction'])
        ->getJson("/api/public/projects/{$this->project->username}/brands-with-conjunctions");

    $response->assertOk()
        ->assertJsonPath('data.groups', []);
});

test('returns primary group with brands when active event exists', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'status' => 'active',
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_conjunction'])
        ->getJson("/api/public/projects/{$this->project->username}/brands-with-conjunctions");

    $response->assertOk()
        ->assertJsonCount(1, 'data.groups')
        ->assertJsonPath('data.groups.0.is_primary', true)
        ->assertJsonPath('data.groups.0.project_username', $this->project->username)
        ->assertJsonPath('data.groups.0.brands_count', 1);
});

test('returns conjunction event brands as additional groups', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $conjunctionProject = Project::factory()->create();
    $conjunctionEvent = Event::factory()->published()->create([
        'project_id' => $conjunctionProject->id,
        'is_active' => true,
    ]);

    // Set up conjunction
    $event->conjunctionEvents()->attach($conjunctionEvent->id, ['order_column' => 1]);

    // Brands for primary event
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $event->id,
        'status' => 'active',
    ]);

    // Brands for conjunction event
    BrandEvent::factory()->count(2)->create([
        'brand_id' => Brand::factory(),
        'event_id' => $conjunctionEvent->id,
        'status' => 'active',
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_conjunction'])
        ->getJson("/api/public/projects/{$this->project->username}/brands-with-conjunctions");

    $response->assertOk()
        ->assertJsonCount(2, 'data.groups')
        ->assertJsonPath('data.groups.0.is_primary', true)
        ->assertJsonPath('data.groups.0.brands_count', 1)
        ->assertJsonPath('data.groups.1.is_primary', false)
        ->assertJsonPath('data.groups.1.project_username', $conjunctionProject->username)
        ->assertJsonPath('data.groups.1.brands_count', 2);
});

test('excludes inactive brands from conjunction groups', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $conjunctionProject = Project::factory()->create();
    $conjunctionEvent = Event::factory()->published()->create([
        'project_id' => $conjunctionProject->id,
    ]);

    $event->conjunctionEvents()->attach($conjunctionEvent->id, ['order_column' => 1]);

    // Active brand
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $conjunctionEvent->id,
        'status' => 'active',
    ]);

    // Draft brand (should be excluded)
    BrandEvent::factory()->create([
        'brand_id' => Brand::factory(),
        'event_id' => $conjunctionEvent->id,
        'status' => 'draft',
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_conjunction'])
        ->getJson("/api/public/projects/{$this->project->username}/brands-with-conjunctions");

    $response->assertOk()
        ->assertJsonPath('data.groups.1.brands_count', 1);
});

test('conjunction events are ordered by order_column', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $projectA = Project::factory()->create(['name' => 'Event A']);
    $eventA = Event::factory()->published()->create([
        'project_id' => $projectA->id,
        'title' => 'Event A',
    ]);

    $projectB = Project::factory()->create(['name' => 'Event B']);
    $eventB = Event::factory()->published()->create([
        'project_id' => $projectB->id,
        'title' => 'Event B',
    ]);

    // Attach B first with order 2, A with order 1
    $event->conjunctionEvents()->attach($eventB->id, ['order_column' => 2]);
    $event->conjunctionEvents()->attach($eventA->id, ['order_column' => 1]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_conjunction'])
        ->getJson("/api/public/projects/{$this->project->username}/brands-with-conjunctions");

    $response->assertOk()
        ->assertJsonCount(3, 'data.groups')
        ->assertJsonPath('data.groups.1.event_title', 'Event A')
        ->assertJsonPath('data.groups.2.event_title', 'Event B');
});

test('active brand can be found in conjunction events', function () {
    $event = Event::factory()->published()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $conjunctionProject = Project::factory()->create();
    $conjunctionEvent = Event::factory()->published()->create([
        'project_id' => $conjunctionProject->id,
    ]);

    $event->conjunctionEvents()->attach($conjunctionEvent->id, ['order_column' => 1]);

    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $conjunctionEvent->id,
        'status' => 'active',
    ]);

    $response = $this->withHeaders(['X-API-Key' => 'pk_test_conjunction'])
        ->getJson("/api/public/projects/{$this->project->username}/brands/{$brand->slug}");

    $response->assertOk()
        ->assertJsonPath('data.brand_name', $brand->name);
});
