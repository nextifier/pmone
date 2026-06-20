<?php

use App\Models\Attendee;
use App\Models\Brand;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use App\Support\QrToken;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
});

function confirmedAttendeeForBadge(Event $event): Attendee
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id, 'tier' => 'VIP']);
    $order = TicketOrder::factory()->confirmed()->create(['event_id' => $event->id]);
    $item = $order->items()->create(['ticket_id' => $ticket->id, 'quantity' => 1, 'unit_price' => 0, 'subtotal' => 0]);

    return $item->attendees()->create(['ticket_id' => $ticket->id, 'name' => 'Visitor One', 'email' => 'v1@example.com']);
}

it('normalizes a verify-URL badge value back to the bare token', function () {
    $token = (string) Str::ulid();

    expect(QrToken::normalize($token))->toBe($token);
    expect(QrToken::normalize("https://pmone.id/v/{$token}"))->toBe($token);
    expect(QrToken::normalize("https://pmone.id/v/{$token}/"))->toBe($token);
    expect(QrToken::normalize("https://pmone.id/v/{$token}?ref=badge"))->toBe($token);
    expect(QrToken::normalize("  {$token}  "))->toBe($token);
    expect(QrToken::normalize(null))->toBe('');
});

it('captures an exhibitor lead from a legacy verify-URL badge', function () {
    $brand = Brand::factory()->create();
    $exhibitor = User::factory()->create(['email_verified_at' => now()]);
    $exhibitor->brands()->attach($brand->id, ['role' => 'owner']);
    $this->actingAs($exhibitor);

    $attendee = confirmedAttendeeForBadge($this->event);
    $url = "/api/exhibitor/brands/{$brand->getRouteKey()}/leads/scan";

    // What a phone camera reads off the printed badge.
    $this->postJson($url, [
        'qr_token' => "https://pmone.id/v/{$attendee->qr_token}",
        'event_id' => $this->event->id,
    ])->assertSuccessful()->assertJsonPath('data.result', 'captured');
});

it('checks in an attendee scanned from a legacy verify-URL badge', function () {
    $scanner = User::factory()->create(['email_verified_at' => now()]);
    $scanner->assignRole('scanner');
    $this->actingAs($scanner);

    $attendee = confirmedAttendeeForBadge($this->event);

    $this->postJson("/api/events/{$this->event->id}/scan/check-in", [
        'qr_token' => "https://pmone.id/v/{$attendee->qr_token}",
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSuccessful()->assertJsonPath('data.result', 'checked_in');

    expect($attendee->fresh()->checked_in_at)->not->toBeNull();
});
