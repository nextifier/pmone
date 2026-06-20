<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketOrderItem>
 */
class TicketOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 4);
        $unitPrice = fake()->randomElement([0, 30000, 60000]);

        return [
            'ticket_order_id' => TicketOrder::factory(),
            'ticket_id' => Ticket::factory(),
            'ticket_session_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'phase_label' => fake()->randomElement(['Pre-sale', 'Normal']),
            'subtotal' => $unitPrice * $quantity,
        ];
    }
}
