<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventDay>
 */
class EventDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $day = fake()->numberBetween(1, 5);

        return [
            'event_id' => Event::factory(),
            'day_number' => $day,
            'date' => now()->addDays($day)->toDateString(),
            'label' => ['en' => "Day {$day}", 'id' => "Hari {$day}"],
            'is_active' => true,
        ];
    }
}
