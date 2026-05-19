<?php

use App\Models\Event;
use App\Models\EventDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
});

it('logs activity with project_id when an EventDocument is created', function () {
    $document = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Booth Plan',
    ]);

    $activity = Activity::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['project_id'] ?? null)->toBe($this->project->id);
});

it('logs activity when EventDocument title or required flag changes', function () {
    $document = EventDocument::factory()->create([
        'event_id' => $this->event->id,
        'title' => 'Old',
        'is_required' => false,
    ]);

    $document->update(['title' => 'New', 'is_required' => true]);

    $activity = Activity::query()
        ->where('subject_type', $document->getMorphClass())
        ->where('subject_id', $document->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['attributes']['title'] ?? null)->toBe('New');
    expect($activity->properties['attributes']['is_required'] ?? null)->toBeTrue();
    expect($activity->properties['project_id'] ?? null)->toBe($this->project->id);
});
