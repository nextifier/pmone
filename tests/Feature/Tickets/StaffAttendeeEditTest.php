<?php

use App\Models\Event;
use App\Models\EventDay;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPricePhase;
use App\Models\TicketSession;
use App\Models\User;
use App\Services\Ticket\AttendeeService;
use App\Services\Ticket\TicketPurchaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
    $this->days = collect(range(1, 4))->map(fn ($n) => EventDay::factory()->create([
        'event_id' => $this->event->id, 'day_number' => $n, 'date' => now()->startOfDay()->addDays($n),
    ]));
    $this->dayPass = Ticket::factory()->create([
        'event_id' => $this->event->id, 'requires_day_selection' => true, 'max_quantity' => null,
    ]);
    $this->dayPass->validDays()->sync($this->days->pluck('id')->all());
    TicketPricePhase::factory()->create([
        'ticket_id' => $this->dayPass->id, 'price' => 0,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    $this->service = app(AttendeeService::class);
});

function dayPassOrder(int $qty, int $dayId): array
{
    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => test()->event->id,
        'buyer_name' => 'Buyer', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => test()->dayPass->id, 'quantity' => $qty, 'selected_event_day_id' => $dayId]],
    ]);

    return [$order, $order->items()->first()];
}

it('updates the item in place when only one attendee holds it', function () {
    [$order, $item] = dayPassOrder(1, $this->days[0]->id);
    $attendee = $item->attendees()->first();

    $this->service->changeDayOrSession($attendee, $this->days[1]->id, '__keep__');

    expect($order->items()->count())->toBe(1)
        ->and($item->fresh()->selected_event_day_id)->toBe($this->days[1]->id);
});

it('splits a shared item so only the moved attendee changes day', function () {
    [$order, $item] = dayPassOrder(2, $this->days[0]->id);
    $attendees = $item->attendees()->orderBy('id')->get();
    $moved = $attendees->first();
    $stayed = $attendees->last();

    $this->service->changeDayOrSession($moved, $this->days[1]->id, '__keep__');

    expect($order->items()->count())->toBe(2)
        ->and($order->attendees()->count())->toBe(2)
        ->and($moved->fresh()->ticketOrderItem->selected_event_day_id)->toBe($this->days[1]->id)
        ->and($moved->fresh()->ticketOrderItem->quantity)->toBe(1)
        ->and($stayed->fresh()->ticketOrderItem->selected_event_day_id)->toBe($this->days[0]->id)
        ->and($stayed->fresh()->ticketOrderItem->quantity)->toBe(1);
});

it('moves a session seat and adjusts booked_count', function () {
    $addOn = Ticket::factory()->create(['event_id' => $this->event->id, 'kind' => 'add_on', 'max_quantity' => null]);
    TicketPricePhase::factory()->create(['ticket_id' => $addOn->id, 'price' => 0, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    $sessionA = TicketSession::factory()->create(['ticket_id' => $addOn->id, 'capacity' => 10, 'is_active' => true]);
    $sessionB = TicketSession::factory()->create(['ticket_id' => $addOn->id, 'capacity' => 10, 'is_active' => true]);

    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => $this->event->id, 'buyer_name' => 'B', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $addOn->id, 'quantity' => 1, 'ticket_session_id' => $sessionA->id]],
    ]);
    $attendee = $order->attendees()->first();

    $this->service->changeDayOrSession($attendee, '__keep__', $sessionB->id);

    expect($sessionA->fresh()->booked_count)->toBe(0)
        ->and($sessionB->fresh()->booked_count)->toBe(1)
        ->and($attendee->fresh()->ticketOrderItem->ticket_session_id)->toBe($sessionB->id);
});

it('blocks moving an attendee into a full session', function () {
    $addOn = Ticket::factory()->create(['event_id' => $this->event->id, 'kind' => 'add_on', 'max_quantity' => null]);
    TicketPricePhase::factory()->create(['ticket_id' => $addOn->id, 'price' => 0, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    $sessionA = TicketSession::factory()->create(['ticket_id' => $addOn->id, 'capacity' => 10, 'is_active' => true]);
    $full = TicketSession::factory()->create(['ticket_id' => $addOn->id, 'capacity' => 1, 'is_active' => true]);

    app(TicketPurchaseService::class)->createOrder([
        'event_id' => $this->event->id, 'buyer_name' => 'X', 'buyer_email' => 'x@example.com', 'buyer_phone' => '08',
        'items' => [['ticket_id' => $addOn->id, 'quantity' => 1, 'ticket_session_id' => $full->id]],
    ]);

    $order = app(TicketPurchaseService::class)->createOrder([
        'event_id' => $this->event->id, 'buyer_name' => 'B', 'buyer_email' => 'b@example.com', 'buyer_phone' => '0812',
        'items' => [['ticket_id' => $addOn->id, 'quantity' => 1, 'ticket_session_id' => $sessionA->id]],
    ]);
    $attendee = $order->attendees()->first();

    expect(fn () => $this->service->changeDayOrSession($attendee, '__keep__', $full->id))
        ->toThrow(HttpException::class);
});

it('toggles check-in on and off', function () {
    [$order, $item] = dayPassOrder(1, $this->days[0]->id);
    $attendee = $item->attendees()->first();
    $staffId = User::factory()->create(['email_verified_at' => now()])->id;

    $this->service->setCheckIn($attendee, true, $staffId, $this->event->id);
    expect($attendee->fresh()->checked_in_at)->not->toBeNull()
        ->and($attendee->fresh()->checked_in_by)->toBe($staffId);

    $this->service->setCheckIn($attendee, false, $staffId, $this->event->id);
    expect($attendee->fresh()->checked_in_at)->toBeNull()
        ->and($attendee->fresh()->checked_in_by)->toBeNull();
});

it('lets staff list and search attendees but forbids non-staff', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    [$order, $item] = dayPassOrder(1, $this->days[0]->id);
    $attendee = $item->attendees()->first();
    $attendee->update(['name' => 'Findable Person']);

    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('staff');

    $this->actingAs($staff)
        ->getJson("/api/events/{$this->event->id}/attendees?search=Findable")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Findable Person');

    $this->actingAs($staff)
        ->patchJson("/api/events/{$this->event->id}/attendees/{$attendee->id}", ['selected_event_day_id' => $this->days[2]->id])
        ->assertOk();

    expect($attendee->fresh()->ticketOrderItem->selected_event_day_id)->toBe($this->days[2]->id);

    $outsider = User::factory()->create(['email_verified_at' => now()]);
    $outsider->assignRole('exhibitor');
    $this->actingAs($outsider)
        ->getJson("/api/events/{$this->event->id}/attendees")
        ->assertForbidden();
});
