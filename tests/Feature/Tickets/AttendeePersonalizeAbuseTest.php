<?php

use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Models\ApiConsumer;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    ApiConsumer::factory()->create(['api_key' => 'pk_personalize', 'is_active' => true]);
    $this->headers = ['X-API-Key' => 'pk_personalize'];

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'max_quantity' => null]);
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->ticket->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);
});

function personalizeAttendee(): Attendee
{
    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => test()->ticket->id, 'quantity' => 1]],
    ]);

    $attendee = $order->attendees()->orderBy('id')->first();
    $attendee->update(['email' => null, 'claimed_by_user_id' => null, 'personalized_at' => null]);

    return $attendee->fresh();
}

it('dispatches the e-ticket once for a single email personalization', function () {
    Bus::fake();
    $attendee = personalizeAttendee();

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", [
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ])
        ->assertOk();

    Bus::assertDispatchedTimes(SendAttendeeETicketJob::class, 1);
});

it('caps e-ticket re-sends per attendee per day', function () {
    Bus::fake();
    $attendee = personalizeAttendee();

    // The per-attendee cap is 3/day. Four distinct email changes should only
    // dispatch three sends; the fourth is skipped but still returns 200.
    foreach (['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com'] as $email) {
        $this->withHeaders($this->headers)
            ->patchJson("/api/public/attendees/{$attendee->ulid}", [
                'name' => 'Jane',
                'email' => $email,
            ])
            ->assertOk();
    }

    Bus::assertDispatchedTimes(SendAttendeeETicketJob::class, 3);
});

it('caps e-ticket re-sends per destination email per day across attendees', function () {
    Bus::fake();

    // Point four different attendees at the same inbox; the per-email cap (3/day)
    // still limits the sends to that address to three.
    foreach (range(1, 4) as $i) {
        $attendee = personalizeAttendee();
        $this->withHeaders($this->headers)
            ->patchJson("/api/public/attendees/{$attendee->ulid}", [
                'name' => 'Jane',
                'email' => 'shared@example.com',
            ])
            ->assertOk();
    }

    Bus::assertDispatchedTimes(SendAttendeeETicketJob::class, 3);
});

it('creates exactly one account for a brand-new email', function () {
    Bus::fake();
    $attendee = personalizeAttendee();

    expect(User::where('email', 'newholder@example.com')->exists())->toBeFalse();

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", [
            'name' => 'New Holder',
            'email' => 'newholder@example.com',
        ])
        ->assertOk();

    expect(User::where('email', 'newholder@example.com')->count())->toBe(1)
        ->and($attendee->fresh()->claimed_by_user_id)->not->toBeNull();
});

it('does not create new accounts once the per-IP daily cap is reached', function () {
    Bus::fake();
    $attendee = personalizeAttendee();

    // Saturate the per-IP new-account cap (20/day) for the test client IP.
    for ($i = 0; $i < 20; $i++) {
        RateLimiter::hit('eticket-new-account:127.0.0.1', 86400);
    }

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", [
            'name' => 'Capped',
            'email' => 'capped@example.com',
        ])
        ->assertOk();

    // Email is still attached to the attendee, but no account was minted.
    expect($attendee->fresh()->email)->toBe('capped@example.com')
        ->and(User::where('email', 'capped@example.com')->exists())->toBeFalse();
});
