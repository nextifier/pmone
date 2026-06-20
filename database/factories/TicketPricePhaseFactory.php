<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketPricePhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketPricePhase>
 */
class TicketPricePhaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'label' => fake()->randomElement(['Pre-registration', 'Pre-sale', 'Normal']),
            'price' => fake()->randomElement([0, 30000, 60000, 100000]),
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(5),
            'quota' => fake()->optional()->numberBetween(10, 200),
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => 0]);
    }
}
