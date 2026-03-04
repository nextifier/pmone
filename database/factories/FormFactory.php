<?php

namespace Database\Factories;

use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Form>
 */
class FormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional(0.7)->paragraph(),
            'settings' => [
                'confirmation_message' => 'Thank you for your response!',
            ],
            'status' => Form::STATUS_DRAFT,
            'is_active' => true,
            'opens_at' => null,
            'closes_at' => null,
            'response_limit' => null,
            'project_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Form::STATUS_PUBLISHED,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Form::STATUS_CLOSED,
        ]);
    }

    public function withSchedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addWeek(),
        ]);
    }

    public function withResponseLimit(int $limit = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'response_limit' => $limit,
        ]);
    }
}
