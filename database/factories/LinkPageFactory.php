<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LinkPage>
 */
class LinkPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'is_active' => true,
            'visibility' => 'public',
            'more_details' => null,
            'settings' => null,
            'order_column' => 0,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_type' => 'website',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function unlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'unlisted',
        ]);
    }
}
