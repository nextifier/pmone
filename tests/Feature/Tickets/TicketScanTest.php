<?php

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->scanner = User::factory()->create(['email_verified_at' => now()]);
    $this->scanner->assignRole('scanner');
    $this->actingAs($this->scanner);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
});

function confirmedAttendee(Event $event, array $validDayIds = [], string $kind = 'entry'): Attendee
{
    $ticket = Ticket::factory()->when($kind === 'add_on', fn ($f) => $f->addOn())->create(['event_id' => $event->id]);
    if ($validDayIds) {
        $ticket->validDays()->sync($validDayIds);
    }
    $order = TicketOrder::factory()->confirmed()->create(['event_id' => $event->id]);
    $item = $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);

    return $item->attendees()->create(['ticket_id' => $ticket->id, 'name' => 'Tamu #1']);
}

it('checks in a valid attendee then warns on re-scan', function () {
    $attendee = confirmedAttendee($this->event);
    $base = "/api/events/{$this->event->id}/scan";

    $this->postJson("{$base}/check-in", ['qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid()])
        ->assertSuccessful()
        ->assertJsonPath('data.result', 'checked_in');

    expect($attendee->fresh()->checked_in_at)->not->toBeNull();

    $this->postJson("{$base}/check-in", ['qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid()])
        ->assertSuccessful()
        ->assertJsonPath('data.result', 'already_checked_in');
});

it('reprints a badge and records the reprint', function () {
    $attendee = confirmedAttendee($this->event);
    $base = "/api/events/{$this->event->id}/scan";

    $this->postJson("{$base}/check-in", ['qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid(), 'action' => 'reprint'])
        ->assertSuccessful()
        ->assertJsonPath('data.result', 'reprinted');

    expect($attendee->fresh()->reprint_count)->toBe(1);
});

it('re-issues a fresh QR token and invalidates the lost one', function () {
    $attendee = confirmedAttendee($this->event);
    $base = "/api/events/{$this->event->id}/scan";
    $oldToken = $attendee->qr_token;

    $this->postJson("{$base}/check-in", ['qr_token' => $oldToken, 'idempotency_key' => (string) Str::uuid(), 'action' => 'reissue'])
        ->assertSuccessful()
        ->assertJsonPath('data.result', 'reprinted');

    $attendee->refresh();
    expect($attendee->qr_token)->not->toBe($oldToken)
        ->and($attendee->reprint_count)->toBe(1);

    // The lost badge's old token no longer resolves.
    $this->postJson("{$base}/check-in", ['qr_token' => $oldToken, 'idempotency_key' => (string) Str::uuid()])
        ->assertJsonPath('data.result', 'invalid')
        ->assertJsonPath('data.reason', 'ticket_not_found');
});

it('exposes print_on_redeem on the scanned attendee', function () {
    $ticket = Ticket::factory()->addOn()->create(['event_id' => $this->event->id, 'print_on_redeem' => true]);
    $order = TicketOrder::factory()->confirmed()->create(['event_id' => $this->event->id]);
    $item = $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);
    $attendee = $item->attendees()->create(['ticket_id' => $ticket->id, 'name' => 'Tamu #1']);

    $this->postJson("/api/events/{$this->event->id}/scan/check-in", ['qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid()])
        ->assertSuccessful()
        ->assertJsonPath('data.attendee.print_on_redeem', true);
});

it('rejects a ticket whose order is not confirmed', function () {
    $attendee = confirmedAttendee($this->event);
    $attendee->ticketOrderItem->ticketOrder->update(['status' => TicketOrderStatus::PendingPayment]);

    $this->postJson("/api/events/{$this->event->id}/scan/check-in", [
        'qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid(),
    ])->assertJsonPath('data.result', 'invalid')->assertJsonPath('data.reason', 'order_not_confirmed');
});

it('blocks cross-event scan unless an allow_cross_scan conjunction exists', function () {
    $attendee = confirmedAttendee($this->event);
    $gateEvent = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);

    // No conjunction yet -> wrong event.
    $this->postJson("/api/events/{$gateEvent->id}/scan/check-in", [
        'qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid(),
    ])->assertJsonPath('data.result', 'invalid')->assertJsonPath('data.reason', 'wrong_event');

    // Link them with cross-scan enabled.
    DB::table('event_conjunctions')->insert([
        'event_id' => $this->event->id, 'conjunction_event_id' => $gateEvent->id,
        'allow_cross_scan' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson("/api/events/{$gateEvent->id}/scan/check-in", [
        'qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid(),
    ])->assertJsonPath('data.result', 'checked_in');
});

it('warns on cross-day when scanning outside the ticket valid days', function () {
    $day = EventDay::factory()->create(['event_id' => $this->event->id, 'day_number' => 1, 'date' => now()->addDays(3)->toDateString()]);
    $attendee = confirmedAttendee($this->event, [$day->id]);

    $this->postJson("/api/events/{$this->event->id}/scan/check-in", [
        'qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid(),
    ])->assertJsonPath('data.result', 'checked_in')->assertJsonPath('data.warning', 'cross_day');
});

it('searches and manually checks in an attendee', function () {
    $attendee = confirmedAttendee($this->event);
    $attendee->update(['name' => 'Searchable Person']);
    $base = "/api/events/{$this->event->id}/scan";

    $this->getJson("{$base}/search?q=Searchable")->assertSuccessful()->assertJsonCount(1, 'data');

    $this->postJson("{$base}/manual-check-in", ['attendee_ulid' => $attendee->ulid, 'idempotency_key' => (string) Str::uuid()])
        ->assertSuccessful()->assertJsonPath('data.result', 'checked_in');
});

it('returns an offline manifest of confirmed attendees', function () {
    confirmedAttendee($this->event);
    confirmedAttendee($this->event);

    $this->getJson("/api/events/{$this->event->id}/scan/manifest")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('syncs a batch of offline scans idempotently and pulls check-ins', function () {
    $attendee = confirmedAttendee($this->event);
    $key = (string) Str::uuid();

    $first = $this->postJson("/api/events/{$this->event->id}/scan/sync", [
        'logs' => [['qr_token' => $attendee->qr_token, 'idempotency_key' => $key, 'action' => 'check_in']],
    ])->assertSuccessful();

    // Same key again -> no duplicate ScanLog row.
    $this->postJson("/api/events/{$this->event->id}/scan/sync", [
        'logs' => [['qr_token' => $attendee->qr_token, 'idempotency_key' => $key, 'action' => 'check_in']],
    ])->assertSuccessful();

    expect(DB::table('scan_logs')->where('idempotency_key', $key)->count())->toBe(1);

    $this->getJson("/api/events/{$this->event->id}/scan/manifest")->assertJsonPath('data.0.checked_in_at', fn ($v) => $v !== null);
});

it('exposes the attendee email on the scan result', function () {
    $attendee = confirmedAttendee($this->event);
    $attendee->update(['email' => 'visitor@example.com']);

    $this->postJson("/api/events/{$this->event->id}/scan/check-in", [
        'qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful()->assertJsonPath('data.attendee.email', 'visitor@example.com');
});

it('returns display-only event context for the scanner shell', function () {
    EventDay::factory()->create(['event_id' => $this->event->id, 'day_number' => 1, 'date' => now()->addDay()->toDateString()]);

    $this->getJson("/api/events/{$this->event->id}/scan/context")
        ->assertSuccessful()
        ->assertJsonPath('data.title', $this->event->title)
        ->assertJsonPath('data.location', $this->event->location)
        ->assertJsonCount(1, 'data.days')
        ->assertJsonPath('data.days.0.day_number', 1);
});

it('forbids the scan context for a user without scan permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');
    $this->actingAs($user);

    $this->getJson("/api/events/{$this->event->id}/scan/context")->assertForbidden();
});

it('forbids a user without scan permission', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('user');
    $this->actingAs($user);

    $attendee = confirmedAttendee($this->event);

    $this->postJson("/api/events/{$this->event->id}/scan/check-in", [
        'qr_token' => $attendee->qr_token, 'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();
});
