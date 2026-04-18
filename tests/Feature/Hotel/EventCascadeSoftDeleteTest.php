<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('soft deleting event soft deletes its hotels', function () {
    $event = Event::factory()->create();
    $hotelA = Hotel::factory()->for($event)->create();
    $hotelB = Hotel::factory()->for($event)->create();

    $event->delete();

    expect(Hotel::find($hotelA->id))->toBeNull();
    expect(Hotel::find($hotelB->id))->toBeNull();
    expect(Hotel::withTrashed()->find($hotelA->id))->not->toBeNull();
    expect(Hotel::withTrashed()->find($hotelB->id))->not->toBeNull();
});

test('restoring event restores its hotels', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->for($event)->create();

    $event->delete();
    expect(Hotel::find($hotel->id))->toBeNull();

    $event->fresh()->restore();

    expect(Hotel::find($hotel->id))->not->toBeNull();
});

test('reservations stay alive when event is soft deleted', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->for($event)->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id]);

    $event->delete();

    expect(Reservation::find($reservation->id))->not->toBeNull();
});
