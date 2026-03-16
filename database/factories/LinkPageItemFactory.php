<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LinkPageItem>
 */
class LinkPageItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'link_page_id' => \App\Models\LinkPage::factory(),
            'label' => fake()->words(3, true),
            'url' => fake()->url(),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
