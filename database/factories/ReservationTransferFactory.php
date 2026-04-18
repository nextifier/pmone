<?php

namespace Database\Factories;

use App\Enums\TransferDirection;
use App\Models\HotelTransferOption;
use App\Models\Reservation;
use App\Models\ReservationTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationTransfer>
 */
class ReservationTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'transfer_option_id' => HotelTransferOption::factory(),
            'direction' => fake()->randomElement([TransferDirection::In, TransferDirection::Out]),
            'transfer_date' => fake()->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'transfer_time' => '10:00:00',
            'pickup_location' => fake()->randomElement(['Soekarno-Hatta Airport', 'Halim Perdanakusuma Airport']),
            'dropoff_location' => 'Hotel',
            'flight_number' => fake()->bothify('??###'),
            'flight_time' => '09:30:00',
            'pax_count' => fake()->numberBetween(1, 4),
            'luggage_count' => fake()->numberBetween(1, 3),
            'price' => fake()->randomElement([200000, 350000, 500000]),
        ];
    }
}
