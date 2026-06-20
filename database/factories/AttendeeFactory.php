<?php

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\Ticket;
use App\Models\TicketOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendee>
 */
class AttendeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_order_item_id' => TicketOrderItem::factory(),
            'ticket_id' => Ticket::factory(),
            'name' => fake()->optional()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->numerify('08##########'),
            'reprint_count' => 0,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'checked_in_at' => now(),
        ]);
    }

    public function personalized(): static
    {
        return $this->state(fn () => [
            'name' => fake()->name(),
            'personalized_at' => now(),
        ]);
    }
}
