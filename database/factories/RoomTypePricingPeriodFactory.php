<?php

namespace Database\Factories;

use App\Models\RoomType;
use App\Models\RoomTypePricingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomTypePricingPeriod>
 */
class RoomTypePricingPeriodFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 days', '+30 days');
        $end = (clone $start)->modify('+'.fake()->numberBetween(1, 4).' days');

        return [
            'room_type_id' => RoomType::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'rate' => fake()->randomElement([850000, 1200000, 1500000, 1800000, 2200000]),
            'label' => fake()->randomElement([null, 'Weekend', 'Peak', 'Off-season']),
            'is_active' => true,
        ];
    }

    public function withRate(float $rate): static
    {
        return $this->state(fn () => ['rate' => $rate]);
    }

    public function forRange(string $start, string $end): static
    {
        return $this->state(fn () => [
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
