<?php

namespace Database\Factories;

use App\Models\BrandEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PromotionPost>
 */
class PromotionPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_event_id' => BrandEvent::factory(),
            'caption' => fake()->optional(0.8)->sentence(),
        ];
    }
}
