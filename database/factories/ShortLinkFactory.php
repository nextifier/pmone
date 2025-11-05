<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShortLink>
 */
class ShortLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'slug' => fake()->unique()->slug(2),
            'destination_url' => fake()->url(),
            'is_active' => true,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_type' => 'website',
        ];
    }

    /**
     * Indicate that the short link has OpenGraph metadata.
     */
    public function withOpenGraph(): static
    {
        return $this->state(fn (array $attributes) => [
            'og_title' => fake()->sentence(),
            'og_description' => fake()->paragraph(),
            'og_image' => fake()->imageUrl(),
            'og_type' => fake()->randomElement(['website', 'article', 'video', 'product']),
        ]);
    }

    /**
     * Indicate that the short link is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
