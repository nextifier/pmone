<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventProduct>
 */
class EventProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'category_id' => EventProductCategory::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.5)->sentence(),
            'price' => fake()->randomFloat(2, 50000, 5000000),
            'unit' => fake()->randomElement(['unit', 'set', 'meter', 'sqm']),
            'booth_types' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forBoothType(string $boothType): static
    {
        return $this->state(fn (array $attributes) => [
            'booth_types' => [$boothType],
        ]);
    }
}
