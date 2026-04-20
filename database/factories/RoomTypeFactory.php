<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => fake()->randomElement(['Deluxe', 'Superior', 'Executive Suite', 'Family Room', 'Standard']),
            'description' => fake()->paragraph(),
            'max_pax' => fake()->randomElement([2, 3, 4]),
            'bed_type' => fake()->randomElement(['King', 'Queen', 'Twin', 'Double']),
            'area_sqm' => fake()->randomFloat(2, 18, 60),
            'base_rate' => fake()->randomElement([850000, 1200000, 1500000, 2200000, 3500000]),
            'breakfast_included' => fake()->boolean(70),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
