<?php

namespace Database\Factories;

use App\Enums\EmailEventType;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => fake()->unique()->uuid(),
            'mailer' => 'ses-v2',
            'from_address' => 'noreply@pmone.id',
            'subject' => fake()->sentence(4),
            'recipients' => [fake()->unique()->safeEmail()],
            'configuration_set' => 'pmone-transactional',
            'status' => EmailEventType::Send,
            'status_rank' => EmailEventType::Send->rank(),
            'sent_at' => now(),
            'last_event_at' => null,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => EmailEventType::Delivery,
            'status_rank' => EmailEventType::Delivery->rank(),
            'last_event_at' => now(),
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => [
            'status' => EmailEventType::Bounce,
            'status_rank' => EmailEventType::Bounce->rank(),
            'last_event_at' => now(),
        ]);
    }
}
