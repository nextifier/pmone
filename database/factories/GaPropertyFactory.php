<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GaProperty>
 */
class GaPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Website',
            'property_id' => fake()->numerify('#########'),
            'account_name' => fake()->company().' Analytics Account',
            'is_active' => true,
            'last_synced_at' => fake()->optional(0.7)->dateTimeBetween('-1 hour', 'now'),
            'sync_frequency' => fake()->randomElement([5, 10, 15, 30]),
            'rate_limit_per_hour' => fake()->randomElement([10, 12, 15, 20]),
        ];
    }

    /**
     * Indicate that the property is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the property has never been synced.
     */
    public function neverSynced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => null,
        ]);
    }

    /**
     * Indicate that the property needs syncing.
     */
    public function needsSync(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_synced_at' => now()->subMinutes($attributes['sync_frequency'] + 5),
        ]);
    }
}
