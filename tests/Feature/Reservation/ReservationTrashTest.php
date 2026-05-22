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

test('trash lists only soft-deleted reservations for the event', function () {
    Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $trashed = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $trashed->delete();

    $this->actingAs($this->admin);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations/trash");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $trashed->id);
});

test('admin can restore a trashed reservation', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $reservation->delete();

    $this->actingAs($this->admin);

    $this->postJson("/api/events/{$this->event->id}/reservations/trash/{$reservation->id}/restore")
        ->assertSuccessful();

    $this->assertNotSoftDeleted('reservations', ['id' => $reservation->id]);
});

test('admin can bulk restore trashed reservations', function () {
    $reservations = Reservation::factory()->count(3)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $reservations->each->delete();

    $this->actingAs($this->admin);

    $this->postJson("/api/events/{$this->event->id}/reservations/trash/restore/bulk", [
        'ids' => $reservations->pluck('id')->all(),
    ])
        ->assertSuccessful()
        ->assertJsonPath('restored_count', 3);

    foreach ($reservations as $reservation) {
        $this->assertNotSoftDeleted('reservations', ['id' => $reservation->id]);
    }
});

test('admin can permanently delete a trashed reservation', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $reservation->delete();

    $this->actingAs($this->admin);

    $this->deleteJson("/api/events/{$this->event->id}/reservations/trash/{$reservation->id}")
        ->assertSuccessful();

    $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
});

test('admin can bulk permanently delete trashed reservations', function () {
    $reservations = Reservation::factory()->count(2)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $reservations->each->delete();

    $this->actingAs($this->admin);

    $this->deleteJson("/api/events/{$this->event->id}/reservations/trash/bulk", [
        'ids' => $reservations->pluck('id')->all(),
    ])
        ->assertSuccessful()
        ->assertJsonPath('deleted_count', 2);

    foreach ($reservations as $reservation) {
        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
    }
});

test('bulk restore does not touch trashed reservations from another event', function () {
    $mine = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $mine->delete();

    $otherEvent = Event::factory()->create();
    $otherHotel = Hotel::factory()->withEvent($otherEvent)->create();
    $foreign = Reservation::factory()->create([
        'hotel_id' => $otherHotel->id,
        'event_id' => $otherEvent->id,
    ]);
    $foreign->delete();

    $this->actingAs($this->admin);

    $this->postJson("/api/events/{$this->event->id}/reservations/trash/restore/bulk", [
        'ids' => [$mine->id, $foreign->id],
    ])
        ->assertSuccessful()
        ->assertJsonPath('restored_count', 1);

    $this->assertNotSoftDeleted('reservations', ['id' => $mine->id]);
    $this->assertSoftDeleted('reservations', ['id' => $foreign->id]);
});

test('user without reservations.delete permission cannot view trash', function () {
    $this->actingAs($this->stranger);

    $this->getJson("/api/events/{$this->event->id}/reservations/trash")
        ->assertForbidden();
});
