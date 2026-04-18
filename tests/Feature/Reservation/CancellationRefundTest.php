<?php

use App\Enums\ReservationStatus;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['reservations.read', 'reservations.cancel', 'reservations.refund'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

test('refund calculation 100% when H-14 or more', function () {
    $hotel = Hotel::factory()->create();
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1000000,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => Carbon::now()->addDays(20),
        'check_out_date' => Carbon::now()->addDays(22),
    ]);

    $service = app(ReservationService::class);
    expect($service->calculateRefund($reservation->fresh('items')))->toBe(1000000.0);
});

test('refund calculation 50% when H-7 to H-13', function () {
    $hotel = Hotel::factory()->create();
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 2000000,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => Carbon::now()->addDays(10),
        'check_out_date' => Carbon::now()->addDays(12),
    ]);

    $service = app(ReservationService::class);
    expect($service->calculateRefund($reservation->fresh('items')))->toBe(1000000.0);
});

test('refund calculation 0 when less than H-7', function () {
    $hotel = Hotel::factory()->create();
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1000000,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => Carbon::now()->addDays(3),
        'check_out_date' => Carbon::now()->addDays(5),
    ]);

    $service = app(ReservationService::class);
    expect($service->calculateRefund($reservation->fresh('items')))->toBe(0.0);
});

test('admin can cancel reservation with custom refund amount', function () {
    Queue::fake();

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1500000,
        'xendit_invoice_id' => 'inv_test',
    ]);

    $response = $this->postJson("/api/events/{$hotel->event_id}/reservations/{$reservation->ulid}/cancel", [
        'reason' => 'Customer requested refund',
        'refund_amount' => 750000,
        'process_refund' => true,
    ]);

    $response->assertSuccessful();
    expect((float) $response->json('refund_amount'))->toBe(750000.0);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled);
    expect((float) $reservation->refund_amount)->toBe(750000.0);
});
