<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\RundownItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RundownItem>
 */
class RundownItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);
        $startHour = fake()->numberBetween(8, 17);
        $duration = fake()->numberBetween(30, 120);

        return [
            'event_id' => Event::factory(),
            'date' => fake()->dateTimeBetween('+1 day', '+5 days')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:%02d', $startHour + intdiv($duration, 60), $duration % 60),
            'title' => ['en' => $title, 'id' => $title],
            'description' => null,
            'theme' => null,
            'location' => ['en' => fake()->word(), 'id' => fake()->word()],
            'presented_by' => null,
            'moderator' => null,
            'panelists' => null,
            'speakers' => null,
            'settings' => [],
            'more_details' => [],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSpeakers(int $count = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'speakers' => collect(range(1, $count))->map(fn () => [
                'name' => fake()->name(),
                'title' => fake()->jobTitle(),
                'organization' => fake()->company(),
            ])->all(),
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
