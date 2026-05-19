<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('soft deleting event keeps hotels alive (global resource)', function () {
    $event = Event::factory()->create();
    $hotelA = Hotel::factory()->withEvent($event)->create();
    $hotelB = Hotel::factory()->withEvent($event)->create();

    $event->delete();

    // Hotels are global - they survive event soft delete
    expect(Hotel::find($hotelA->id))->not->toBeNull();
    expect(Hotel::find($hotelB->id))->not->toBeNull();
});

test('pivot rows survive event soft delete', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();

    $event->delete();

    // Pivot row still exists because soft delete does not cascade FK
    $this->assertDatabaseHas('hotel_event', ['hotel_id' => $hotel->id, 'event_id' => $event->id]);
});

test('force deleting event cascades pivot rows', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();

    $event->forceDelete();

    // Cascade FK fires on hard delete - pivot rows removed
    $this->assertDatabaseMissing('hotel_event', ['hotel_id' => $hotel->id, 'event_id' => $event->id]);
    // Hotel itself stays
    expect(Hotel::find($hotel->id))->not->toBeNull();
});

test('reservations stay alive when event is soft deleted', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'event_id' => $event->id,
    ]);

    $event->delete();

    expect(Reservation::find($reservation->id))->not->toBeNull();
});
