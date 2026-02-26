<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $editionNumber = fake()->numberBetween(1, 30);
        $startDate = fake()->dateTimeBetween('+1 month', '+1 year');
        $startDate->setTime(10, 0);
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(1, 5).' days');
        $endDate->setTime(18, 0);

        return [
            'project_id' => Project::factory(),
            'title' => fake()->company().' Expo',
            'edition_number' => $editionNumber,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'location' => fake()->optional(0.8)->address(),
            'location_link' => fake()->optional(0.5)->url(),
            'hall' => fake()->optional(0.5)->randomElement(['Hall A', 'Hall B', 'Hall A1-A2', 'Hall C']),
            'status' => 'draft',
            'visibility' => 'private',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'visibility' => 'public',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }

    public function withOrderDeadline(?string $deadline = null): static
    {
        return $this->state(fn (array $attributes) => [
            'order_form_deadline' => $deadline ?? now()->addDays(30),
        ]);
    }

    public function withPromotionPostDeadline(?string $deadline = null): static
    {
        return $this->state(fn (array $attributes) => [
            'promotion_post_deadline' => $deadline ?? now()->addDays(30),
        ]);
    }
}
