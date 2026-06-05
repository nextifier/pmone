<?php

namespace Database\Factories;

use App\Models\LinkPage;
use App\Models\LinkPageBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkPageBanner>
 */
class LinkPageBannerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_page_id' => LinkPage::factory(),
            'url' => fake()->optional()->url(),
            'caption' => fake()->optional()->words(3, true),
            'is_active' => true,
            'sort_order' => 0,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
