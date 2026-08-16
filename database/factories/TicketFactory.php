<?php

namespace Database\Factories;

use App\Enums\Ticketing\PurchaseType;
use App\Enums\Ticketing\TicketKind;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(['Regular', 'VIP', 'Student', 'Early Bird']).' '.fake()->numberBetween(1, 99);

        return [
            'event_id' => Event::factory(),
            'kind' => TicketKind::Entry,
            'title' => ['en' => $title, 'id' => $title],
            'tier' => fake()->randomElement(['Regular', 'VIP', 'Premium']),
            'benefits' => ['Entry access', 'Goodie bag'],
            'currency' => 'IDR',
            'purchase_type' => PurchaseType::FirstParty,
            'print_on_redeem' => false,
            'stock' => fake()->optional()->numberBetween(50, 500),
            'min_quantity' => 1,
            // No cap by default. This used to roll a random 2-10, so any test
            // buying three tickets failed whenever the die came up 2 - which is
            // what made TicketPurchaseTest fail once in a while and pass on the
            // retry. Tests that exercise the cap set it with maxQuantity().
            'max_quantity' => null,
            'is_active' => true,
        ];
    }

    /**
     * A ticket that caps how many one buyer may take.
     */
    public function maxQuantity(int $max): static
    {
        return $this->state(fn () => [
            'max_quantity' => $max,
        ]);
    }

    public function addOn(): static
    {
        return $this->state(fn () => [
            'kind' => TicketKind::AddOn,
            'tier' => null,
        ]);
    }

    public function external(): static
    {
        return $this->state(fn () => [
            'purchase_type' => PurchaseType::External,
            'external_url' => fake()->url(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
