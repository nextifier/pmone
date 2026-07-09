<?php

namespace Database\Factories;

use App\Enums\EmailEventType;
use App\Models\EmailEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailEvent>
 */
class EmailEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => fake()->unique()->uuid(),
            'type' => EmailEventType::Delivery,
            'recipient' => fake()->safeEmail(),
            'subtype' => null,
            'diagnostic' => null,
            'occurred_at' => now(),
            'payload' => [],
        ];
    }

    public function bounce(): static
    {
        return $this->state(fn () => [
            'type' => EmailEventType::Bounce,
            'subtype' => 'General',
            'diagnostic' => 'smtp; 550 5.1.1 user unknown',
            'payload' => ['bounceType' => 'Permanent'],
        ]);
    }
}
