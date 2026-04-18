<?php

namespace Database\Factories;

use App\Enums\TransferDirection;
use App\Models\Hotel;
use App\Models\HotelTransferOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelTransferOption>
 */
class HotelTransferOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'label' => fake()->randomElement([
                'Airport Transfer (Sedan)',
                'Airport Transfer (MPV)',
                'City Transfer',
                'Venue Shuttle',
            ]),
            'direction' => fake()->randomElement(TransferDirection::cases()),
            'vehicle_type' => fake()->randomElement(['Sedan', 'MPV', 'Van', 'Bus']),
            'max_pax' => fake()->randomElement([2, 4, 6, 12]),
            'price' => fake()->randomElement([200000, 350000, 500000, 750000]),
            'is_active' => true,
        ];
    }
}
