<?php

namespace Database\Factories;

use App\Enums\Ticketing\TicketOrderStatus;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketOrder>
 */
class TicketOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomElement([0, 30000, 60000, 120000]);

        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => TicketOrderStatus::PendingPayment,
            'buyer_name' => fake()->name(),
            'buyer_email' => fake()->safeEmail(),
            'buyer_phone' => fake()->numerify('08##########'),
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total' => $subtotal,
            'source' => 'public',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => TicketOrderStatus::Confirmed,
            'paid_at' => now(),
        ]);
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'subtotal' => 0,
            'total' => 0,
            'status' => TicketOrderStatus::Confirmed,
        ]);
    }
}
