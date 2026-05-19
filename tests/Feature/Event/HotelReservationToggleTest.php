<?php

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['events.update', 'hotels.read', 'reservations.read'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create(['status' => 'active', 'username' => 'acme']);
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'acme-2026',
        'hotel_reservation_enabled' => false,
    ]);
});

test('toggle requires authentication', function () {
    auth()->logout();

    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => true,
    ])->assertStatus(401);
});

test('toggle rejects payload without enabled field', function () {
    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['enabled']);
});

test('toggle returns 422 when no active payment gateway', function () {
    $response = $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => true,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error_code', 'PAYMENT_GATEWAY_REQUIRED')
        ->assertJsonPath('payment_gateways_url', '/projects/acme/settings/payment-gateways');

    expect($this->event->fresh()->hotel_reservation_enabled)->toBeFalse();
});

test('toggle enables when payment gateway is active', function () {
    ProjectPaymentGateway::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);

    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => true,
    ])->assertSuccessful()
        ->assertJsonPath('data.hotel_reservation_enabled', true);

    expect($this->event->fresh()->hotel_reservation_enabled)->toBeTrue();
});

test('toggle disables without requiring payment gateway', function () {
    $this->event->update(['hotel_reservation_enabled' => true]);

    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => false,
    ])->assertSuccessful()
        ->assertJsonPath('data.hotel_reservation_enabled', false);

    expect($this->event->fresh()->hotel_reservation_enabled)->toBeFalse();
});

test('event-scoped hotel endpoints return 404 when feature disabled', function () {
    $hotel = Hotel::factory()->withEvent($this->event)->create();

    $this->getJson("/api/events/{$this->event->id}/hotels")
        ->assertStatus(404)
        ->assertJsonPath('error_code', 'HOTEL_RESERVATION_DISABLED');

    $this->getJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}")
        ->assertStatus(404)
        ->assertJsonPath('error_code', 'HOTEL_RESERVATION_DISABLED');
});

test('event-scoped hotel endpoints work when feature enabled', function () {
    $this->event->update(['hotel_reservation_enabled' => true]);
    ProjectPaymentGateway::factory()->create([
        'project_id' => $this->project->id,
        'is_active' => true,
    ]);
    Hotel::factory()->withEvent($this->event)->create();

    $this->getJson("/api/events/{$this->event->id}/hotels")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('event-scoped hotel endpoints return 404 when feature enabled but no gateway', function () {
    $this->event->update(['hotel_reservation_enabled' => true]);
    $hotel = Hotel::factory()->withEvent($this->event)->create();

    $this->getJson("/api/events/{$this->event->id}/hotels")
        ->assertStatus(404)
        ->assertJsonPath('error_code', 'HOTEL_RESERVATION_DISABLED');

    $this->getJson("/api/events/{$this->event->id}/hotels/{$hotel->slug}")
        ->assertStatus(404)
        ->assertJsonPath('error_code', 'HOTEL_RESERVATION_DISABLED');
});

test('event-scoped reservations endpoint returns 404 when feature disabled', function () {
    $this->getJson("/api/events/{$this->event->id}/reservations")
        ->assertStatus(404)
        ->assertJsonPath('error_code', 'HOTEL_RESERVATION_DISABLED');
});

test('disable blocked when active future reservations exist (without force)', function () {
    $this->event->update(['hotel_reservation_enabled' => true]);
    $hotel = Hotel::factory()->withEvent($this->event)->create();
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);
    $reservation = Reservation::factory()->create([
        'event_id' => $this->event->id,
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => now()->addDays(5),
        'check_out_date' => now()->addDays(7),
    ]);

    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => false,
    ])->assertStatus(409)
        ->assertJsonPath('error_code', 'ACTIVE_RESERVATIONS_EXIST')
        ->assertJsonPath('active_reservations_count', 1);

    expect($this->event->fresh()->hotel_reservation_enabled)->toBeTrue();
});

test('disable proceeds when force flag is true even with active reservations', function () {
    $this->event->update(['hotel_reservation_enabled' => true]);
    $hotel = Hotel::factory()->withEvent($this->event)->create();
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);
    $reservation = Reservation::factory()->create([
        'event_id' => $this->event->id,
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => now()->addDays(5),
        'check_out_date' => now()->addDays(7),
    ]);

    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => false,
        'force' => true,
    ])->assertSuccessful();

    expect($this->event->fresh()->hotel_reservation_enabled)->toBeFalse();
});

test('disable allowed without warning when only past reservations exist', function () {
    $this->event->update(['hotel_reservation_enabled' => true]);
    $hotel = Hotel::factory()->withEvent($this->event)->create();
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);
    $reservation = Reservation::factory()->create([
        'event_id' => $this->event->id,
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Paid,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => now()->subDays(10),
        'check_out_date' => now()->subDays(8),
    ]);

    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => false,
    ])->assertSuccessful();

    expect($this->event->fresh()->hotel_reservation_enabled)->toBeFalse();
});

test('toggle authorization respects events.update permission', function () {
    $reader = User::factory()->create(['email_verified_at' => now()]);
    $readerRole = Role::firstOrCreate(['name' => 'reader', 'guard_name' => 'web']);
    $readerRole->syncPermissions([]);
    $reader->assignRole('reader');
    $this->actingAs($reader);

    $this->patchJson('/api/projects/acme/events/acme-2026/hotel-reservation-toggle', [
        'enabled' => true,
    ])->assertStatus(403);
});
