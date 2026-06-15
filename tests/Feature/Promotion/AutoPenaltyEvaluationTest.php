<?php

use App\Enums\AdjustmentKind;
use App\Enums\AdjustmentValueType;
use App\Enums\PenaltyTriggerType;
use App\Enums\StackingMode;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\PromotionRule;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Services\Promotion\PenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeBookingForPenalty(string $checkIn, string $checkOut): Reservation
{
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create([
        'tax_percentage' => 0,
        'service_charge_percentage' => 0,
    ]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id, 'base_rate' => 1_000_000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->id,
        'quantity' => 10,
        'start_date' => '2026-01-01',
        'end_date' => '2027-12-31',
        'is_active' => true,
    ]);

    $reservation = Reservation::create([
        'reservation_number' => 'HTL-'.uniqid(),
        'event_id' => $event->id,
        'hotel_id' => $hotel->id,
        'guest_name' => 'Test',
        'guest_email' => 'test@example.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'subtotal_rooms' => 1_000_000,
        'subtotal_transfer' => 0,
        'surcharge_amount' => 0,
        'penalty_amount' => 0,
        'tax_amount' => 0,
        'service_charge_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'magic_link_token' => bin2hex(random_bytes(32)),
        'magic_link_expires_at' => now()->addDays(90),
    ]);

    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
        'rate_per_night' => 1_000_000,
        'nights' => 1,
        'qty' => 1,
        'subtotal' => 1_000_000,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
    ]);

    return $reservation->fresh(['items', 'transfers', 'hotel']);
}

it('B14: evaluateAndApply matches target_types stored as basename', function () {
    // B14: stored target_types = ["Reservation"] (basename) must match a Reservation
    // morphed entity even when getMorphClass() returns the FQN.
    $reservation = makeBookingForPenalty(now()->addDays(1)->toDateString(), now()->addDays(2)->toDateString());

    PromotionRule::factory()->create([
        'name' => 'Late Booking Fee',
        'kind' => AdjustmentKind::Penalty,
        'value_type' => AdjustmentValueType::Percentage,
        'value' => 10,
        'stacking_mode' => StackingMode::CombinableWithAll,
        'priority' => 100,
        'is_active' => true,
        'target_types' => ['Reservation'], // basename, not FQN
        'trigger_type' => PenaltyTriggerType::LeadTime,
        'trigger_config' => ['max_days' => 3, 'operator' => 'lt'],
    ]);

    $applied = app(PenaltyService::class)->evaluateAndApply($reservation);

    expect($applied)->toHaveCount(1);
});

it('B13: evaluateAndApply skips rule outside starts_at/ends_at window', function () {
    $reservation = makeBookingForPenalty(now()->addDays(1)->toDateString(), now()->addDays(2)->toDateString());

    // Rule that already expired (ends_at past)
    PromotionRule::factory()->create([
        'name' => 'Expired Late Fee',
        'kind' => AdjustmentKind::Penalty,
        'value_type' => AdjustmentValueType::Percentage,
        'value' => 10,
        'stacking_mode' => StackingMode::CombinableWithAll,
        'is_active' => true,
        'starts_at' => now()->subDays(30),
        'ends_at' => now()->subDay(),
        'target_types' => ['Reservation'],
        'trigger_type' => PenaltyTriggerType::LeadTime,
        'trigger_config' => ['max_days' => 30, 'operator' => 'lt'],
    ]);

    $applied = app(PenaltyService::class)->evaluateAndApply($reservation);

    expect($applied)->toHaveCount(0);
});

it('B13: evaluateAndApply skips rule whose starts_at is in the future', function () {
    $reservation = makeBookingForPenalty(now()->addDays(1)->toDateString(), now()->addDays(2)->toDateString());

    PromotionRule::factory()->create([
        'name' => 'Future Late Fee',
        'kind' => AdjustmentKind::Penalty,
        'value_type' => AdjustmentValueType::Percentage,
        'value' => 10,
        'stacking_mode' => StackingMode::CombinableWithAll,
        'is_active' => true,
        'starts_at' => now()->addDay(),
        'ends_at' => null,
        'target_types' => ['Reservation'],
        'trigger_type' => PenaltyTriggerType::LeadTime,
        'trigger_config' => ['max_days' => 30, 'operator' => 'lt'],
    ]);

    $applied = app(PenaltyService::class)->evaluateAndApply($reservation);

    expect($applied)->toHaveCount(0);
});

it('event-scoped cancellation fee only applies the reservation own event rule', function () {
    $reservation = makeBookingForPenalty(now()->addDay()->toDateString(), now()->addDays(2)->toDateString());

    $ownRule = PromotionRule::factory()->create([
        'name' => 'Own Event Cancellation',
        'kind' => AdjustmentKind::Penalty,
        'value_type' => AdjustmentValueType::Percentage,
        'value' => 50,
        'stacking_mode' => StackingMode::CombinableWithAll,
        'is_active' => true,
        'target_types' => ['Reservation'],
        'trigger_type' => PenaltyTriggerType::CancellationWindow,
        'trigger_config' => ['min_days' => 7, 'operator' => 'lt'],
        'event_id' => $reservation->event_id,
        'priority' => 100,
    ]);

    // A different event's cancellation rule, higher priority (lower number) so it would be
    // picked first if event scoping leaked across events.
    $otherEvent = Event::factory()->create();
    PromotionRule::factory()->create([
        'name' => 'Other Event Cancellation',
        'kind' => AdjustmentKind::Penalty,
        'value_type' => AdjustmentValueType::Percentage,
        'value' => 90,
        'stacking_mode' => StackingMode::CombinableWithAll,
        'is_active' => true,
        'target_types' => ['Reservation'],
        'trigger_type' => PenaltyTriggerType::CancellationWindow,
        'trigger_config' => ['min_days' => 7, 'operator' => 'lt'],
        'event_id' => $otherEvent->id,
        'priority' => 1,
    ]);

    $adj = app(PenaltyService::class)->applyCancellationFee($reservation);

    expect($adj)->not->toBeNull();
    expect($adj->promotion_rule_id)->toBe($ownRule->id);
    // subtotal 1.000.000 -> own 50% = 500.000 (never the other event's 90%)
    expect((float) $adj->amount)->toBe(500000.0);
});
