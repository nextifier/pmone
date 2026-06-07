<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);
        $description = fake()->sentence(12);

        return [
            'event_id' => Event::factory(),
            'title' => ['en' => $title, 'id' => $title],
            'description' => ['en' => $description, 'id' => $description],
            'icon' => null,
            'settings' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withIcon(string $icon = 'hugeicons:mic-01'): static
    {
        return $this->state(fn (array $attributes) => [
            'icon' => $icon,
        ]);
    }
}
