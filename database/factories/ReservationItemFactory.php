<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationItem>
 */
class ReservationItemFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 week', '+1 month');
        $nights = fake()->numberBetween(1, 4);
        $checkOut = (clone $checkIn)->modify('+'.$nights.' days');
        $rate = fake()->randomElement([850000, 1200000, 1500000]);
        $qty = fake()->numberBetween(1, 2);
        $subtotal = $rate * $nights * $qty;

        return [
            'reservation_id' => Reservation::factory(),
            'room_type_id' => RoomType::factory(),
            'allotment_id' => null,
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'nights' => $nights,
            'qty' => $qty,
            'guest_name' => fake()->name(),
            'rate_per_night' => $rate,
            'subtotal' => $subtotal,
        ];
    }
}
