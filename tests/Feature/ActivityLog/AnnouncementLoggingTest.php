<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

it('logs activity when an Announcement is created', function () {
    $announcement = Announcement::create([
        'title' => 'Welcome',
        'type' => 'info',
        'status' => 'published',
        'is_global' => true,
        'is_dismissible' => true,
    ]);

    expect(Activity::query()
        ->where('subject_type', $announcement->getMorphClass())
        ->where('subject_id', $announcement->id)
        ->where('event', 'created')
        ->exists()
    )->toBeTrue();
});

it('logs activity when an Announcement is updated with changed fields', function () {
    $announcement = Announcement::create([
        'title' => 'Original Title',
        'type' => 'info',
        'status' => 'draft',
        'is_global' => false,
        'is_dismissible' => true,
    ]);

    $announcement->update(['title' => 'New Title', 'status' => 'published']);

    $activity = Activity::query()
        ->where('subject_type', $announcement->getMorphClass())
        ->where('subject_id', $announcement->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['attributes']['title'] ?? null)->toBe('New Title');
    expect($activity->properties['attributes']['status'] ?? null)->toBe('published');
});
