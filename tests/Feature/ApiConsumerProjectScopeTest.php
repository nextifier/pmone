<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->projectA = Project::factory()->create(['username' => 'project-a', 'status' => 'active']);
    $this->projectB = Project::factory()->create(['username' => 'project-b', 'status' => 'active']);
});

it('lets an unscoped consumer read any project unchanged', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_unscoped']);

    $this->withHeaders(['X-API-Key' => 'pk_test_unscoped'])
        ->getJson("/api/public/projects/{$this->projectA->username}")
        ->assertSuccessful();

    $this->withHeaders(['X-API-Key' => 'pk_test_unscoped'])
        ->getJson("/api/public/projects/{$this->projectB->username}")
        ->assertSuccessful();
});

it('lets a consumer scoped to a project read that project', function () {
    $consumer = ApiConsumer::factory()->create(['api_key' => 'pk_test_scoped_allowed']);
    $consumer->projects()->sync([$this->projectA->id]);

    $this->withHeaders(['X-API-Key' => 'pk_test_scoped_allowed'])
        ->getJson("/api/public/projects/{$this->projectA->username}")
        ->assertSuccessful();
});

it('blocks a scoped consumer from reading a project outside its scope', function () {
    $consumer = ApiConsumer::factory()->create(['api_key' => 'pk_test_scoped_blocked']);
    $consumer->projects()->sync([$this->projectA->id]);

    $this->withHeaders(['X-API-Key' => 'pk_test_scoped_blocked'])
        ->getJson("/api/public/projects/{$this->projectB->username}")
        ->assertStatus(403);
});

it('blocks a scoped consumer from an event-slug-keyed route outside its scope', function () {
    // Exercises the eventSlug-resolution branch of ValidateApiKey: the
    // route carries no {username}, only {eventSlug}, so the middleware
    // must resolve the owning project through the Event before enforcing
    // scope. Runs ahead of the tickets-enabled gate, so it 403s regardless
    // of that event's tickets_enabled state.
    $eventB = Event::factory()->create(['project_id' => $this->projectB->id, 'slug' => 'event-b']);

    $consumer = ApiConsumer::factory()->create(['api_key' => 'pk_test_scoped_event_blocked']);
    $consumer->projects()->sync([$this->projectA->id]);

    $this->withHeaders(['X-API-Key' => 'pk_test_scoped_event_blocked'])
        ->getJson("/api/public/events/{$eventB->slug}/tickets")
        ->assertStatus(403);
});

it('reports isProjectAllowed and hasProjectScope correctly at the model level', function () {
    $consumer = ApiConsumer::factory()->create();

    expect($consumer->hasProjectScope())->toBeFalse()
        ->and($consumer->isProjectAllowed($this->projectA->username))->toBeTrue()
        ->and($consumer->isProjectAllowed($this->projectB->username))->toBeTrue();

    $consumer->projects()->sync([$this->projectA->id]);
    $consumer->unsetRelation('projects');

    expect($consumer->hasProjectScope())->toBeTrue()
        ->and($consumer->isProjectAllowed($this->projectA->username))->toBeTrue()
        ->and($consumer->isProjectAllowed($this->projectB->username))->toBeFalse();
});
