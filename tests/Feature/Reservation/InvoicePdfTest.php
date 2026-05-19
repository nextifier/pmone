<?php

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['reservations.read', 'reservations.view_documents'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    AppSetting::set('branding', [
        'company_name' => 'PM One Test',
        'address' => 'Jakarta',
        'email' => 'test@pmone.id',
    ]);

    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);
});

test('admin can download invoice pdf', function () {
    $reservation = Reservation::factory()->create(['hotel_id' => $this->hotel->id, 'event_id' => $this->event->id]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    $response = $this->get("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/invoice.pdf");

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('admin cannot download receipt before payment', function () {
    $reservation = Reservation::factory()->create(['hotel_id' => $this->hotel->id, 'event_id' => $this->event->id]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    $response = $this->get("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/receipt.pdf");

    $response->assertStatus(422);
});

test('admin can download receipt after payment', function () {
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $this->hotel->id, 'event_id' => $this->event->id]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    $response = $this->get("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/receipt.pdf");

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});
