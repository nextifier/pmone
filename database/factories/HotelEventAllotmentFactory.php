<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelEventAllotment>
 */
class HotelEventAllotmentFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 month', '+3 months');
        $end = (clone $start)->modify('+'.fake()->numberBetween(2, 5).' days');

        return [
            'hotel_id' => Hotel::factory(),
            'room_type_id' => RoomType::factory(),
            'quantity' => fake()->numberBetween(5, 30),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'release_at' => null,
            'surcharge_type' => null,
            'surcharge_amount' => null,
            'is_active' => true,
        ];
    }

    public function withSurcharge(string $type = 'percentage', float $amount = 10.00): static
    {
        return $this->state(fn () => [
            'surcharge_type' => $type,
            'surcharge_amount' => $amount,
        ]);
    }
}
