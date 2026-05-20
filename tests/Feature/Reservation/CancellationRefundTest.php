<?php

use App\Enums\AdjustmentValueType;
use App\Enums\PenaltyTriggerType;
use App\Enums\ReservationStatus;
use App\Models\Hotel;
use App\Models\PromotionRule;
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

    $response = $this->postJson("/api/events/{$reservation->event_id}/reservations/{$reservation->ulid}/cancel", [
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

test('cancellation fee does not inflate the refund', function () {
    Queue::fake();

    // A cancellation-window penalty rule that fires for this reservation.
    PromotionRule::factory()->penalty()->create([
        'trigger_type' => PenaltyTriggerType::CancellationWindow->value,
        'trigger_config' => ['min_days' => 30, 'operator' => 'lt'],
        'value_type' => AdjustmentValueType::FixedAmount->value,
        'value' => 500000,
    ]);

    $hotel = Hotel::factory()->create();
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1000000,
        'xendit_invoice_id' => 'inv_test',
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => Carbon::now()->addDays(20),
        'check_out_date' => Carbon::now()->addDays(22),
    ]);

    $response = $this->postJson("/api/events/{$reservation->event_id}/reservations/{$reservation->ulid}/cancel", [
        'reason' => 'Customer requested',
        'process_refund' => false,
    ]);

    $response->assertSuccessful();

    // The cancellation fee penalty must actually have been applied - otherwise
    // this test would not exercise the order-dependent bug at all.
    expect($reservation->fresh()->adjustments()->count())->toBe(1);

    // Guest paid 1,000,000 (H-20 -> 100% tier). The refund must equal that
    // exactly - never inflated by the 500,000 cancellation fee.
    expect((float) $response->json('refund_amount'))->toBe(1000000.0);
    expect((float) $reservation->fresh()->refund_amount)->toBe(1000000.0);
});

test('cancel rejects already-cancelled reservation (T4 idempotency)', function () {
    Queue::fake();

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->cancelled()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1000000,
    ]);

    $response = $this->postJson("/api/events/{$reservation->event_id}/reservations/{$reservation->ulid}/cancel", [
        'reason' => 'Trying to cancel again',
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Cannot cancel');
});

test('cancel rejects refunded reservation (T4 idempotency)', function () {
    Queue::fake();

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'status' => ReservationStatus::Refunded,
        'refunded_at' => now()->subDay(),
        'total_amount' => 1000000,
    ]);

    $this->postJson("/api/events/{$reservation->event_id}/reservations/{$reservation->ulid}/cancel", [
        'reason' => 'Cancel after refund',
    ])->assertStatus(422);
});
