<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectBanner>
 */
class ProjectBannerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'placement' => 'hero',
            'type' => 'image',
            'title' => fake()->optional()->words(3, true),
            'description' => null,
            'link' => fake()->optional()->url(),
            'cta_label' => null,
            'aspect_ratio' => '4:1',
            'is_active' => true,
            'sort_order' => 0,
            'start_time' => null,
            'end_time' => null,
            'more_details' => null,
            'settings' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'title' => fake()->sentence(4),
            'description' => '<p>'.fake()->sentence(10).'</p>',
            'cta_label' => fake()->words(2, true),
            'link' => '/book-space',
            'aspect_ratio' => null,
        ]);
    }

    public function imageText(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image_text',
            'title' => fake()->sentence(4),
            'description' => '<p>'.fake()->sentence(10).'</p>',
            'cta_label' => fake()->words(2, true),
            'link' => '/book-space',
        ]);
    }
}
