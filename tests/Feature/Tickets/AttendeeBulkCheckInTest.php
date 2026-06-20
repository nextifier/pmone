<?php

use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->day = EventDay::factory()->create(['event_id' => $this->event->id, 'day_number' => 1, 'date' => now()->addDay()]);
    $this->ticket = Ticket::factory()->create([
        'event_id' => $this->event->id, 'requires_day_selection' => true, 'max_quantity' => null,
    ]);
    $this->ticket->validDays()->sync([$this->day->id]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $this->staff = User::factory()->create(['email_verified_at' => now()]);
    $this->staff->assignRole('staff');
});

function bulkCheckInAttendees(int $qty): Collection
{
    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => test()->ticket->id, 'quantity' => $qty, 'selected_event_day_id' => test()->day->id]],
    ]);

    return $order->attendees()->orderBy('id')->get();
}

it('bulk checks in attendees', function () {
    $attendees = bulkCheckInAttendees(3);

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/attendees/bulk-check-in", [
            'ids' => $attendees->pluck('id')->all(),
            'checked_in' => true,
        ])
        ->assertOk()
        ->assertJsonPath('updated_count', 3);

    $attendees->each(function ($a) {
        $fresh = $a->fresh();
        expect($fresh->checked_in_at)->not->toBeNull()
            ->and($fresh->checked_in_by)->toBe($this->staff->id)
            ->and($fresh->checkin_event_id)->toBe($this->event->id);
    });
});

it('bulk un-checks attendees', function () {
    $attendees = bulkCheckInAttendees(2);
    $attendees->each->update([
        'checked_in_at' => now(), 'checked_in_by' => $this->staff->id, 'checkin_event_id' => $this->event->id,
    ]);

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/attendees/bulk-check-in", [
            'ids' => $attendees->pluck('id')->all(),
            'checked_in' => false,
        ])
        ->assertOk()
        ->assertJsonPath('updated_count', 2);

    $attendees->each(fn ($a) => expect($a->fresh()->checked_in_at)->toBeNull());
});

it('counts only attendees whose state actually changed', function () {
    $attendees = bulkCheckInAttendees(2);
    $attendees->first()->update([
        'checked_in_at' => now(), 'checked_in_by' => $this->staff->id, 'checkin_event_id' => $this->event->id,
    ]);

    $this->actingAs($this->staff)
        ->postJson("/api/events/{$this->event->id}/attendees/bulk-check-in", [
            'ids' => $attendees->pluck('id')->all(),
            'checked_in' => true,
        ])
        ->assertOk()
        ->assertJsonPath('updated_count', 1);
});

it('forbids non-staff from bulk check-in', function () {
    $attendees = bulkCheckInAttendees(1);
    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('exhibitor');

    $this->actingAs($outsider)
        ->postJson("/api/events/{$this->event->id}/attendees/bulk-check-in", [
            'ids' => $attendees->pluck('id')->all(),
            'checked_in' => true,
        ])
        ->assertForbidden();
});
