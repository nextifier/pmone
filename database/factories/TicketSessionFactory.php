<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketSession>
 */
class TicketSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(2)->setTime(12, 0);

        return [
            'ticket_id' => Ticket::factory()->addOn(),
            'label' => $start->format('j M H:i'),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addMinutes(15),
            'location' => fake()->optional()->randomElement(['Main Stage', 'Hall A', 'Workshop Room']),
            'host' => fake()->optional()->name(),
            'capacity' => fake()->optional()->numberBetween(10, 100),
            'is_active' => true,
        ];
    }
}
