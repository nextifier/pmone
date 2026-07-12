<?php

namespace Database\Factories;

use App\Enums\Ticketing\TicketWaitlistEntryStatus;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketWaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketWaitlistEntry>
 */
class TicketWaitlistEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'ticket_id' => Ticket::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
            'quantity' => 1,
            'status' => TicketWaitlistEntryStatus::Waiting,
            'position' => fake()->numberBetween(1, 1000),
            'offered_at' => null,
            'offer_expires_at' => null,
            'claim_token' => null,
        ];
    }

    public function offered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketWaitlistEntryStatus::Offered,
            'offered_at' => now(),
            'offer_expires_at' => now()->addMinutes(60),
            'claim_token' => TicketWaitlistEntry::generateClaimToken(),
        ]);
    }
}
