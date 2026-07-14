<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

function manifestConfirmedAttendee(Event $event, string $name): Attendee
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    $order = TicketOrder::factory()->confirmed()->create(['event_id' => $event->id]);
    $item = $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);

    return $item->attendees()->create(['ticket_id' => $ticket->id, 'name' => $name]);
}

/**
 * @return array{0: TicketOrder, 1: Attendee}
 */
function manifestPendingOrderWithAttendee(Event $event, string $name): array
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    $order = TicketOrder::factory()->create(['event_id' => $event->id]);
    $item = $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);
    $attendee = $item->attendees()->create(['ticket_id' => $ticket->id, 'name' => $name]);

    return [$order, $attendee];
}

it('pages through every confirmed attendee exactly once and terminates', function () {
    $expected = collect(range(1, 5))
        ->map(fn (int $i) => manifestConfirmedAttendee($this->event, "Guest {$i}")->qr_token)
        ->sort()->values()->all();

    $base = "/api/events/{$this->event->id}/scan/manifest";
    $seen = collect();
    $cursor = null;
    $pages = 0;

    do {
        $query = ['limit' => 2] + ($cursor !== null ? ['cursor' => $cursor] : []);
        $response = $this->getJson($base.'?'.http_build_query($query))->assertSuccessful();

        $seen = $seen->merge(collect($response->json('data'))->pluck('qr_token'));
        $cursor = $response->json('next_cursor');
        $pages++;
    } while ($cursor !== null && $pages < 20);

    expect($seen)->toHaveCount(5)
        ->and($seen->unique()->count())->toBe(5)
        ->and($seen->sort()->values()->all())->toEqual($expected)
        ->and($pages)->toBe(3);
});

it('carries the stable ulid key and a version cursor in a paged response', function () {
    $attendee = manifestConfirmedAttendee($this->event, 'Guest');

    $this->getJson("/api/events/{$this->event->id}/scan/manifest?paged=1")
        ->assertSuccessful()
        ->assertJsonPath('data.0.ulid', $attendee->ulid)
        ->assertJsonPath('next_cursor', null)
        ->assertJsonPath('version', fn ($v) => is_string($v) && $v !== '');
});

it('still returns the full manifest when no paging params are given', function () {
    manifestConfirmedAttendee($this->event, 'One');
    manifestConfirmedAttendee($this->event, 'Two');

    $this->getJson("/api/events/{$this->event->id}/scan/manifest")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.ulid', fn ($v) => is_string($v) && $v !== '')
        ->assertJsonMissingPath('next_cursor');
});

it('returns tagged deltas for a confirmed, cancelled, and reissued attendee since a cursor', function () {
    Queue::fake();

    $stable = manifestConfirmedAttendee($this->event, 'Stays');
    $toCancel = manifestConfirmedAttendee($this->event, 'Cancelled');
    $toReissue = manifestConfirmedAttendee($this->event, 'Reissued');
    [$pendingOrder, $toConfirm] = manifestPendingOrderWithAttendee($this->event, 'Confirmed');

    // Everything above predates the cursor; only changes after it should surface.
    $since = now();
    $this->travel(1)->minute();

    $service = app(TicketPurchaseService::class);
    $service->markAsConfirmed($pendingOrder->fresh());
    $service->refundAttendee($toCancel->fresh());

    $rotatedToken = (string) Str::ulid();
    $toReissue->forceFill(['qr_token' => $rotatedToken])->save();

    $response = $this->getJson(
        "/api/events/{$this->event->id}/scan/manifest/changes?since=".urlencode($since->toIso8601String())
    )->assertSuccessful();

    $changes = collect($response->json('data'))->keyBy('ulid');

    // A now-confirmed order joins the manifest - this only appears because the
    // status flip bumps the attendee's updated_at (touchOrderAttendees).
    expect($changes)->toHaveKey($toConfirm->ulid)
        ->and($changes[$toConfirm->ulid]['action'])->toBe('upsert')
        ->and($changes[$toConfirm->ulid]['qr_token'])->toBe($toConfirm->fresh()->qr_token);

    // A voided seat leaves the manifest, keyed by the stable ulid (its rotated
    // qr_token is never emitted).
    expect($changes[$toCancel->ulid]['action'])->toBe('remove')
        ->and($changes[$toCancel->ulid])->not->toHaveKey('qr_token');

    // A reissued badge stays admissible but under a new qr_token.
    expect($changes[$toReissue->ulid]['action'])->toBe('upsert')
        ->and($changes[$toReissue->ulid]['qr_token'])->toBe($rotatedToken);

    // An untouched attendee never appears in the delta.
    expect($changes->has($stable->ulid))->toBeFalse();
});

it('scopes the delta to scannable events only', function () {
    $foreignEvent = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    manifestConfirmedAttendee($foreignEvent, 'Foreign');
    $mine = manifestConfirmedAttendee($this->event, 'Mine');

    $response = $this->getJson(
        "/api/events/{$this->event->id}/scan/manifest/changes?since=".urlencode(now()->subMinute()->toIso8601String())
    )->assertSuccessful();

    $ulids = collect($response->json('data'))->pluck('ulid');

    expect($ulids)->toContain($mine->ulid)
        ->and($ulids)->toHaveCount(1);
});
