<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventProductCategory>
 */
class EventProductCategoryFactory extends Factory
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
            'title' => fake()->randomElement([
                'Layanan Listrik',
                'Audio Visual',
                'Furnitur',
                'Internet & Telekomunikasi',
                'Dekorasi',
            ]),
            'description' => fake()->optional(0.5)->paragraph(),
        ];
    }
}
