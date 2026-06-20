<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventCustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventCustomField>
 */
class EventCustomFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => fake()->randomElement(['Position', 'Company Type', 'Interests', 'Budget Range']),
            'type' => fake()->randomElement(['text', 'select', 'multi_select', 'number']),
            'options' => null,
            'required' => false,
            'is_active' => true,
        ];
    }
}
