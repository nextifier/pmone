<?php

namespace Database\Factories;

use App\Enums\IdentityType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $rooms = fake()->randomElement([850000, 1200000, 1500000]) * fake()->numberBetween(1, 3);
        $tax = round($rooms * 0.11, 2);
        $total = $rooms + $tax;

        // Default factory chain: create event + hotel attached via pivot, then set event_id.
        $event = Event::factory()->create();
        $hotel = Hotel::factory()->create();
        $hotel->events()->syncWithoutDetaching([$event->id => ['is_active' => true]]);

        return [
            'reservation_number' => 'HTL-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
            'event_id' => $event->id,
            'hotel_id' => $hotel->id,
            'status' => ReservationStatus::PendingPayment,
            'payment_expires_at' => now()->addHours(24),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->unique()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),
            'guest_identity_type' => IdentityType::Nik,
            'guest_identity_number' => fake()->numerify('################'),
            'guest_nationality' => 'Indonesia',
            'subtotal_rooms' => $rooms,
            'subtotal_transfer' => 0,
            'surcharge_amount' => 0,
            'tax_amount' => $tax,
            'service_charge_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $total,
            'magic_link_token' => hash('sha256', Str::random(40)),
            'source' => ReservationSource::PublicWebsite,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Paid,
            'paid_at' => now(),
            'xendit_invoice_id' => 'inv_test_'.Str::random(16),
            'payment_method' => 'xendit',
        ]);
    }

    public function voucherSent(): static
    {
        return $this->paid()->state(fn () => [
            'status' => ReservationStatus::VoucherSent,
            'voucher_sent_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Expired,
            'payment_expires_at' => now()->subHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->paid()->state(fn () => [
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Customer requested',
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'source' => ReservationSource::AdminManual,
        ]);
    }
}
