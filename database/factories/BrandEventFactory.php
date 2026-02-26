<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BrandEvent>
 */
class BrandEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'event_id' => Event::factory(),
            'booth_number' => fake()->optional(0.7)->numerify('B-###'),
            'booth_size' => fake()->optional(0.5)->randomFloat(2, 4, 100),
            'booth_type' => fake()->optional(0.5)->randomElement(['raw_space', 'standard_shell_scheme', 'enhanced_shell_scheme']),
            'status' => 'draft',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
