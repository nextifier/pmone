<?php

use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Jobs\Ticket\SendTicketOrderConfirmationJob;
use App\Mail\Ticket\TicketOrderConfirmationMail;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketPricePhase;
use App\Models\User;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_dash']);
    $this->headers = ['X-API-Key' => 'pk_test_dash'];
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->service = app(TicketPurchaseService::class);
});

function freeOrderFor(Event $event, TicketPurchaseService $service, string $email = 'buyer@example.com', int $qty = 2): TicketOrder
{
    $ticket = Ticket::factory()->create(['event_id' => $event->id]);
    TicketPricePhase::factory()->free()->create(['ticket_id' => $ticket->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

    return $service->createOrder([
        'event_id' => $event->id,
        'buyer_name' => 'Buyer',
        'buyer_email' => $email,
        'buyer_phone' => '0812',
        'items' => [['ticket_id' => $ticket->id, 'quantity' => $qty]],
    ]);
}

it('dispatches the confirmation job when a free order confirms', function () {
    Bus::fake();
    freeOrderFor($this->event, $this->service);
    Bus::assertDispatched(SendTicketOrderConfirmationJob::class);
});

it('sends a confirmation email carrying a working magic link', function () {
    Mail::fake();
    $order = freeOrderFor($this->event, $this->service);

    (new SendTicketOrderConfirmationJob($order->id))->handle();

    Mail::assertSent(TicketOrderConfirmationMail::class, function ($mail) use ($order) {
        return $mail->order->id === $order->id && str_contains($mail->magicLinkUrl, '/tickets/order/');
    });
});

it('resolves an order from its magic link token', function () {
    $order = freeOrderFor($this->event, $this->service);
    $token = TicketOrder::magicLinkTokenFor($order->order_number);

    $this->withHeaders($this->headers)
        ->getJson("/api/public/ticket-orders/magic/{$token}")
        ->assertSuccessful()
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonCount(2, 'data.attendees');

    $this->withHeaders($this->headers)
        ->getJson('/api/public/ticket-orders/magic/wrong-token')
        ->assertNotFound();
});

it('shows an attendee e-ticket by its ulid', function () {
    $order = freeOrderFor($this->event, $this->service);
    $attendee = $order->attendees()->first();

    $this->withHeaders($this->headers)
        ->getJson("/api/public/attendees/{$attendee->ulid}")
        ->assertSuccessful()
        ->assertJsonPath('data.qr_token', $attendee->qr_token)
        ->assertJsonPath('event.slug', $this->event->slug);
});

it('personalizes an attendee and claims it to the holder by email', function () {
    $order = freeOrderFor($this->event, $this->service);
    $attendee = $order->attendees()->orderBy('id')->skip(1)->first(); // a "Tamu #n" placeholder

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", [
            'name' => 'Real Name',
            'email' => 'holder@example.com',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Real Name')
        ->assertJsonPath('data.is_personalized', true);

    $attendee->refresh();
    expect($attendee->claimed_by_user_id)->not->toBeNull();
    expect(User::where('email', 'holder@example.com')->exists())->toBeTrue();
});

it('auto-sends the e-ticket when the holder adds their email while personalizing', function () {
    Bus::fake();
    $order = freeOrderFor($this->event, $this->service);
    $attendee = $order->attendees()->orderBy('id')->skip(1)->first(); // placeholder, no email

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", [
            'name' => 'Real Name',
            'email' => 'holder@example.com',
        ])
        ->assertSuccessful();

    Bus::assertDispatched(SendAttendeeETicketJob::class, fn ($job) => $job->attendeeId === $attendee->id);
});

it('does not auto-send an e-ticket when only the name is personalized', function () {
    Bus::fake();
    $order = freeOrderFor($this->event, $this->service);
    $attendee = $order->attendees()->orderBy('id')->skip(1)->first();

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", ['name' => 'Just Renamed'])
        ->assertSuccessful();

    Bus::assertNotDispatched(SendAttendeeETicketJob::class);
});

it('locks personalization after check-in', function () {
    $order = freeOrderFor($this->event, $this->service);
    $attendee = $order->attendees()->first();
    $attendee->update(['checked_in_at' => now()]);

    $this->withHeaders($this->headers)
        ->patchJson("/api/public/attendees/{$attendee->ulid}", ['name' => 'Too Late'])
        ->assertStatus(422);
});

it('lists the buyer orders and held tickets in the visitor dashboard', function () {
    $buyer = User::factory()->create(['email' => 'me@example.com', 'email_verified_at' => now()]);
    $order = freeOrderFor($this->event, $this->service, 'me@example.com');

    $this->actingAs($buyer);

    $this->getJson('/api/my/ticket-orders')
        ->assertSuccessful()
        ->assertJsonPath('data.0.order_number', $order->order_number);

    // Claim one ticket to the buyer so My Tickets shows it.
    $order->attendees()->first()->update(['claimed_by_user_id' => $buyer->id]);

    $this->getJson('/api/my/tickets')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event.id', $this->event->id)
        ->assertJsonPath('data.0.event.slug', $this->event->slug);
});

it('updates the visitor profile and reports completeness', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'gender' => null, 'country' => null]);
    $this->actingAs($user);

    $this->getJson('/api/my/ticket-profile')->assertSuccessful();

    $this->patchJson('/api/my/ticket-profile', [
        'country' => 'Indonesia',
        'city' => 'Jakarta',
        'profession' => 'Engineer',
        'business_matching_opt_in' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.country', 'Indonesia')
        ->assertJsonPath('data.business_matching_opt_in', true);

    expect($user->fresh()->profile_completeness)->toBeGreaterThan(0);
});
