<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['reservations.read', 'reservations.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->givePermissionTo(['reservations.read', 'reservations.delete']);

    $this->stranger = User::factory()->create(['email_verified_at' => now()]);
    $this->stranger->givePermissionTo(['reservations.read']);
});

test('admin can bulk delete reservations for an event', function () {
    $reservations = Reservation::factory()->count(3)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/events/{$this->event->id}/reservations/bulk", [
        'ids' => $reservations->pluck('id')->all(),
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('deleted_count', 3);

    foreach ($reservations as $reservation) {
        $this->assertSoftDeleted('reservations', ['id' => $reservation->id]);
    }
});

test('bulk delete does not touch reservations from another event', function () {
    $mine = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $otherEvent = Event::factory()->create();
    $otherHotel = Hotel::factory()->withEvent($otherEvent)->create();
    $foreign = Reservation::factory()->create([
        'hotel_id' => $otherHotel->id,
        'event_id' => $otherEvent->id,
    ]);

    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/events/{$this->event->id}/reservations/bulk", [
        'ids' => [$mine->id, $foreign->id],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('deleted_count', 1);

    $this->assertSoftDeleted('reservations', ['id' => $mine->id]);
    $this->assertNotSoftDeleted('reservations', ['id' => $foreign->id]);
});

test('user without reservations.delete permission cannot bulk delete', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->stranger);

    $response = $this->deleteJson("/api/events/{$this->event->id}/reservations/bulk", [
        'ids' => [$reservation->id],
    ]);

    $response->assertForbidden();

    $this->assertNotSoftDeleted('reservations', ['id' => $reservation->id]);
});

test('bulk delete rejects an empty ids payload', function () {
    $this->actingAs($this->admin);

    $this->deleteJson("/api/events/{$this->event->id}/reservations/bulk", ['ids' => []])
        ->assertStatus(422);
});

test('admin can delete a single reservation', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->admin);

    $response = $this->deleteJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}");

    $response->assertSuccessful();
    $this->assertSoftDeleted('reservations', ['id' => $reservation->id]);
});
