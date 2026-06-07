<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\MediaCoverage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaCoverage>
 */
class MediaCoverageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'title' => fake()->sentence(8),
            'url' => fake()->url(),
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'settings' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
