<?php

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
    ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);

    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->roomType = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);

    $this->reader = User::factory()->create(['email_verified_at' => now()]);
    $this->reader->givePermissionTo('reservations.read');
});

/**
 * Create a reservation for the test event/hotel with the given room items.
 *
 * @param  array<string, mixed>  $attrs
 * @param  array<int, array<string, mixed>>  $items
 */
function makeAnalyticsReservation(array $attrs, array $items = []): Reservation
{
    $reservation = Reservation::factory()->create(array_merge([
        'event_id' => test()->event->id,
        'hotel_id' => test()->hotel->id,
    ], $attrs));

    foreach ($items as $item) {
        ReservationItem::factory()->create(array_merge([
            'reservation_id' => $reservation->id,
            'room_type_id' => test()->roomType->id,
        ], $item));
    }

    return $reservation;
}

it('returns summary KPIs for the event', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 20,
        'is_active' => true,
    ]);

    makeAnalyticsReservation(
        ['status' => ReservationStatus::Paid, 'total_amount' => 2000000, 'payment_channel' => 'BCA'],
        [['nights' => 2, 'qty' => 1, 'subtotal' => 2000000]],
    );
    makeAnalyticsReservation(
        ['status' => ReservationStatus::Paid, 'total_amount' => 3000000, 'payment_channel' => 'QRIS'],
        [['nights' => 3, 'qty' => 2, 'subtotal' => 3000000]],
    );
    makeAnalyticsReservation(
        ['status' => ReservationStatus::PendingPayment, 'total_amount' => 1000000, 'payment_expires_at' => now()->addDay()],
        [['nights' => 1, 'qty' => 1, 'subtotal' => 1000000]],
    );

    $summary = $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary")
        ->assertOk()
        ->assertJsonPath('data.total_reservations', 3)
        ->assertJsonPath('data.paid_reservations', 2)
        ->assertJsonPath('data.pending_reservations', 1)
        ->assertJsonPath('data.total_revenue', 5000000)
        ->assertJsonPath('data.room_nights', 8)
        ->assertJsonPath('data.avg_booking_value', 2500000)
        ->assertJsonPath('data.total_allotment', 20)
        // paid rooms (1 + 2) + active pending (1) = 4 committed of 20 => 20%.
        ->assertJsonPath('data.rooms_sold', 4)
        ->assertJsonPath('data.currency', 'IDR')
        ->json('data');

    expect((float) $summary['occupancy_rate'])->toBe(20.0);
});

it('returns the full detail payload with breakdowns', function () {
    makeAnalyticsReservation(
        ['status' => ReservationStatus::Paid, 'total_amount' => 2000000, 'payment_channel' => 'BCA', 'guest_nationality' => 'Indonesia'],
        [['nights' => 2, 'qty' => 1, 'subtotal' => 2000000]],
    );
    makeAnalyticsReservation(
        ['status' => ReservationStatus::PendingPayment, 'total_amount' => 1000000, 'source' => ReservationSource::AdminManual],
        [['nights' => 1, 'qty' => 1, 'subtotal' => 1000000]],
    );

    $data = $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKeys([
        'summary', 'bookings_over_time', 'by_status', 'by_hotel', 'by_room_type',
        'payment_channels', 'by_source', 'stay_lengths', 'by_nationality', 'top_guests',
    ]);

    $statuses = collect($data['by_status'])->keyBy('status');
    expect($statuses['paid']['count'])->toBe(1);
    expect($statuses['pending_payment']['count'])->toBe(1);

    // Hotel/room/revenue breakdowns only count the paid reservation.
    expect($data['by_hotel'])->toHaveCount(1);
    expect($data['by_hotel'][0]['room_nights'])->toBe(2);
    expect((float) $data['by_hotel'][0]['revenue'])->toBe(2000000.0);

    expect($data['by_room_type'])->toHaveCount(1);
    expect($data['by_room_type'][0]['name'])->not->toBe('Unknown room');
    expect($data['by_room_type'][0]['nights'])->toBe(2);

    expect(collect($data['payment_channels'])->pluck('channel'))->toContain('BCA');

    $sources = collect($data['by_source'])->keyBy('source');
    expect($sources['public_website']['count'])->toBe(1);
    expect($sources['admin_manual']['count'])->toBe(1);

    expect($data['stay_lengths'][0])->toMatchArray(['nights' => 2, 'count' => 1]);
    expect($data['top_guests'])->toHaveCount(1);
});

it('counts revenue and room nights only for paid reservations', function () {
    makeAnalyticsReservation(
        ['status' => ReservationStatus::Paid, 'total_amount' => 4000000, 'payment_channel' => 'BCA'],
        [['nights' => 2, 'qty' => 1, 'subtotal' => 4000000]],
    );
    // Cancelled reservation must not contribute revenue or nights.
    makeAnalyticsReservation(
        ['status' => ReservationStatus::Cancelled, 'total_amount' => 9000000],
        [['nights' => 5, 'qty' => 2, 'subtotal' => 9000000]],
    );

    $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary")
        ->assertOk()
        ->assertJsonPath('data.total_revenue', 4000000)
        ->assertJsonPath('data.room_nights', 2)
        ->assertJsonPath('data.cancelled_reservations', 1);
});

it('clamps occupancy to 100 percent when bookings exceed the allotment', function () {
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 1,
        'is_active' => true,
    ]);

    makeAnalyticsReservation(
        ['status' => ReservationStatus::Paid, 'total_amount' => 5000000, 'payment_channel' => 'BCA'],
        [['nights' => 2, 'qty' => 5, 'subtotal' => 5000000]],
    );

    $summary = $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary")
        ->assertOk()
        ->assertJsonPath('data.rooms_sold', 5)
        ->assertJsonPath('data.total_allotment', 1)
        ->json('data');

    expect((float) $summary['occupancy_rate'])->toBe(100.0);
});

it('narrows the summary to reservations created inside date_from/date_to', function () {
    makeAnalyticsReservation(
        [
            'status' => ReservationStatus::Paid,
            'total_amount' => 2000000,
            'payment_channel' => 'BCA',
            'created_at' => now()->subDays(20),
        ],
        [['nights' => 2, 'qty' => 1, 'subtotal' => 2000000]],
    );
    makeAnalyticsReservation(
        [
            'status' => ReservationStatus::Paid,
            'total_amount' => 3000000,
            'payment_channel' => 'QRIS',
            'created_at' => now()->subDays(2),
        ],
        [['nights' => 3, 'qty' => 1, 'subtotal' => 3000000]],
    );

    $from = now()->subDays(5)->toDateString();
    $to = now()->toDateString();

    $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary?date_from={$from}&date_to={$to}")
        ->assertOk()
        ->assertJsonPath('data.total_reservations', 1)
        ->assertJsonPath('data.paid_reservations', 1)
        ->assertJsonPath('data.total_revenue', 3000000);
});

it('keeps the full history when the range params are absent', function () {
    makeAnalyticsReservation(
        ['status' => ReservationStatus::Paid, 'total_amount' => 2000000, 'created_at' => now()->subDays(200)],
        [['nights' => 2, 'qty' => 1, 'subtotal' => 2000000]],
    );

    $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary")
        ->assertOk()
        ->assertJsonPath('data.total_reservations', 1);
});

it('rejects a date_to before date_from', function () {
    $from = now()->toDateString();
    $to = now()->subDays(3)->toDateString();

    $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary?date_from={$from}&date_to={$to}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to']);
});

it('404s when hotel reservations are disabled for the project', function () {
    $this->project->update(['hotel_reservation_enabled' => false]);

    $this->actingAs($this->reader)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary")
        ->assertNotFound()
        ->assertJsonPath('error_code', 'HOTEL_RESERVATION_DISABLED');
});

it('forbids analytics without the reservations.read permission', function () {
    $outsider = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($outsider)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics/summary")
        ->assertForbidden();

    $this->actingAs($outsider)
        ->getJson("/api/events/{$this->event->id}/reservations/analytics")
        ->assertForbidden();
});
