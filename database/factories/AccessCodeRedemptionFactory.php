<?php

namespace Database\Factories;

use App\Models\AccessCode;
use App\Models\AccessCodeRedemption;
use App\Models\TicketOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessCodeRedemption>
 */
class AccessCodeRedemptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'access_code_id' => AccessCode::factory(),
            'ticket_order_id' => TicketOrder::factory(),
            'applied_adjustment_id' => null,
            'email' => $this->faker->safeEmail(),
            'redeemed_at' => null,
            'voided_at' => null,
        ];
    }

    public function consumed(): static
    {
        return $this->state(fn () => ['redeemed_at' => now()]);
    }

    public function voided(): static
    {
        return $this->state(fn () => ['voided_at' => now()]);
    }
}
