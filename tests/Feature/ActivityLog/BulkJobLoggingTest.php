<?php

use App\Jobs\BulkSoftDeleteGuests;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
});

it('logs bulk_deleted activity with actual deleted count for BulkSoftDeleteGuests', function () {
    $guests = Guest::factory()->count(3)->create(['event_id' => $this->event->id]);
    $ids = $guests->pluck('id')->all();

    (new BulkSoftDeleteGuests(
        jobId: (string) Str::uuid(),
        guestIds: $ids,
        deletedBy: $this->user->id,
    ))->handle();

    $activity = Activity::query()
        ->where('event', 'bulk_deleted')
        ->where('causer_id', $this->user->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['model_type'] ?? null)->toBe('Guest');
    expect($activity->properties['deleted_count'] ?? null)->toBe(3);
});

it('does not log activity when BulkSoftDeleteGuests deletes zero records', function () {
    (new BulkSoftDeleteGuests(
        jobId: (string) Str::uuid(),
        guestIds: [],
        deletedBy: $this->user->id,
    ))->handle();

    expect(Activity::query()->where('event', 'bulk_deleted')->exists())->toBeFalse();
});
