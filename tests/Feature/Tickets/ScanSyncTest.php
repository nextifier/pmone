<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

/**
 * @return Collection<int, Attendee>
 */
function syncBatchAttendees(Event $event, int $count): Collection
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    $order = TicketOrder::factory()->confirmed()->create(['event_id' => $event->id]);

    return collect(range(1, $count))->map(function (int $i) use ($order, $ticket) {
        $item = $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);

        return $item->attendees()->create(['ticket_id' => $ticket->id, 'name' => "Tamu #{$i}"]);
    });
}

/**
 * @return array<string, mixed>
 */
function syncLogFor(Attendee $attendee, ?string $scannedAt = null): array
{
    return array_filter([
        'qr_token' => $attendee->qr_token,
        'idempotency_key' => (string) Str::uuid(),
        'action' => 'check_in',
        'scanned_at' => $scannedAt,
    ], fn ($v) => $v !== null);
}

it('applies a 450-entry offline batch across three 200-max chunks', function () {
    $attendees = syncBatchAttendees($this->event, 450);
    $chunks = $attendees->chunk(200);

    $totalApplied = 0;
    foreach ($chunks as $chunk) {
        $logs = $chunk->map(fn (Attendee $a) => syncLogFor($a))->values()->all();

        $response = $this->postJson("/api/events/{$this->event->id}/scan/sync", ['logs' => $logs])
            ->assertSuccessful();

        $totalApplied += count($response->json('applied'));
    }

    expect($totalApplied)->toBe(450)
        ->and(Attendee::query()->whereIn('id', $attendees->pluck('id'))->whereNotNull('checked_in_at')->count())->toBe(450);
});

it('rejects a single sync batch larger than the 200-entry cap', function () {
    $attendees = syncBatchAttendees($this->event, 201);
    $logs = $attendees->map(fn (Attendee $a) => syncLogFor($a))->values()->all();

    $this->postJson("/api/events/{$this->event->id}/scan/sync", ['logs' => $logs])
        ->assertStatus(422);
});

it('honors a client scanned_at within the 24h skew bound', function () {
    $attendee = syncBatchAttendees($this->event, 1)->first();
    $clientTime = now()->subHours(2);

    $this->postJson("/api/events/{$this->event->id}/scan/sync", [
        'logs' => [syncLogFor($attendee, $clientTime->toIso8601String())],
    ])->assertSuccessful();

    $storedScannedAt = DB::table('scan_logs')->where('attendee_id', $attendee->id)->value('scanned_at');

    expect($attendee->fresh()->checked_in_at->diffInSeconds($clientTime))->toBeLessThan(2)
        ->and(Carbon::parse($storedScannedAt)->diffInSeconds($clientTime))->toBeLessThan(2);
});

it('clamps a client scanned_at that is skewed more than 24h to server time', function () {
    $attendee = syncBatchAttendees($this->event, 1)->first();
    $skewedTime = now()->subHours(48);

    $this->postJson("/api/events/{$this->event->id}/scan/sync", [
        'logs' => [syncLogFor($attendee, $skewedTime->toIso8601String())],
    ])->assertSuccessful();

    expect($attendee->fresh()->checked_in_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('returns per-log results for a duplicate and a wrong-event scan in the same sync batch', function () {
    $attendee = syncBatchAttendees($this->event, 1)->first();
    $gateEvent = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $foreignAttendee = syncBatchAttendees($gateEvent, 1)->first();

    $dupKey = (string) Str::uuid();

    $response = $this->postJson("/api/events/{$this->event->id}/scan/sync", [
        'logs' => [
            ['qr_token' => $attendee->qr_token, 'idempotency_key' => $dupKey, 'action' => 'check_in'],
            // Same idempotency_key resynced in the same batch - firstOrCreate
            // collapses to one ScanLog row, but the second call still sees the
            // now-set checked_in_at and correctly reports the repeat.
            ['qr_token' => $attendee->qr_token, 'idempotency_key' => $dupKey, 'action' => 'check_in'],
            ['qr_token' => $foreignAttendee->qr_token, 'idempotency_key' => (string) Str::uuid(), 'action' => 'check_in'],
        ],
    ])->assertSuccessful();

    $applied = $response->json('applied');

    expect($applied[0]['result'])->toBe('checked_in')
        ->and($applied[1]['result'])->toBe('already_checked_in')
        ->and($applied[2]['result'])->toBe('invalid')
        ->and(DB::table('scan_logs')->where('idempotency_key', $dupKey)->count())->toBe(1);
});
