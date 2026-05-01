<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(2),
            'icon' => 'hugeicons:notification-02',
            'type' => $this->faker->randomElement(['info', 'warning', 'success', 'error', 'marketing']),
            'status' => 'published',
            'is_global' => true,
            'target_roles' => null,
            'cta_actions' => null,
            'more_details' => null,
            'settings' => null,
            'start_time' => null,
            'end_time' => null,
            'is_dismissible' => true,
            'order_column' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }

    public function withCta(): static
    {
        return $this->state(fn () => [
            'cta_actions' => [
                ['label' => 'Learn more', 'url' => '/help', 'style' => 'button-primary', 'icon' => null],
            ],
        ]);
    }

    public function targetingRoles(array $roles): static
    {
        return $this->state(fn () => [
            'is_global' => false,
            'target_roles' => $roles,
        ]);
    }
}
