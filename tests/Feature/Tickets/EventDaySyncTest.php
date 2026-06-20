<?php

use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\User;
use App\Services\Ticket\EventDayService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'tickets_enabled' => true,
        'timezone' => 'Asia/Jakarta',
        'start_date' => '2026-05-28 09:00:00',
        'end_date' => '2026-05-30 17:00:00',
    ]);
    $this->service = app(EventDayService::class);
});

it('derives one event day per calendar day in the range', function () {
    $days = $this->service->syncFromEventDates($this->event->fresh());

    expect($days)->toHaveCount(3)
        ->and($days->pluck('date')->map->toDateString()->all())->toBe(['2026-05-28', '2026-05-29', '2026-05-30'])
        ->and($days->pluck('day_number')->all())->toBe([1, 2, 3])
        ->and($days->every(fn ($d) => $d->is_active))->toBeTrue();
});

it('is idempotent and keeps day ids stable across re-syncs', function () {
    $firstIds = $this->service->syncFromEventDates($this->event->fresh())->pluck('id')->all();
    $again = $this->service->syncFromEventDates($this->event->fresh());

    expect($again->pluck('id')->all())->toBe($firstIds)
        ->and(EventDay::where('event_id', $this->event->id)->count())->toBe(3);
});

it('deactivates out-of-range days instead of deleting them when the range shrinks', function () {
    $this->service->syncFromEventDates($this->event->fresh());
    $lastDay = EventDay::where('event_id', $this->event->id)->orderBy('date')->get()->last();

    // Observer re-syncs on date change.
    $this->event->update(['end_date' => '2026-05-29 17:00:00']);

    expect($lastDay->fresh()->trashed())->toBeFalse()
        ->and($lastDay->fresh()->is_active)->toBeFalse()
        ->and(EventDay::where('event_id', $this->event->id)->where('is_active', true)->count())->toBe(2)
        ->and(EventDay::where('event_id', $this->event->id)->count())->toBe(3);
});

it('re-activates a day that comes back into range', function () {
    $this->service->syncFromEventDates($this->event->fresh());

    $this->event->update(['end_date' => '2026-05-29 17:00:00']);
    $this->event->update(['end_date' => '2026-05-30 17:00:00']);

    expect(EventDay::where('event_id', $this->event->id)->where('is_active', true)->count())->toBe(3);
});

it('does not auto-sync when tickets are disabled', function () {
    $this->event->update(['tickets_enabled' => false]);
    EventDay::where('event_id', $this->event->id)->delete();

    $this->event->update(['end_date' => '2026-06-02 17:00:00']);

    expect(EventDay::where('event_id', $this->event->id)->count())->toBe(0);
});

it('exposes a sync endpoint and an active-toggle endpoint', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('staff');

    $this->actingAs($staff)
        ->postJson("/api/events/{$this->event->id}/event-days/sync")
        ->assertOk()
        ->assertJsonPath('meta.total', 3);

    $days = EventDay::where('event_id', $this->event->id)->orderBy('date')->get();

    $this->actingAs($staff)
        ->postJson("/api/events/{$this->event->id}/event-days/active", ['active_ids' => [$days[0]->id, $days[2]->id]])
        ->assertOk();

    expect($days[0]->fresh()->is_active)->toBeTrue()
        ->and($days[1]->fresh()->is_active)->toBeFalse()
        ->and($days[2]->fresh()->is_active)->toBeTrue();
});
