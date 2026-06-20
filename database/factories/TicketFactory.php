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
            'max_quantity' => fake()->optional()->numberBetween(2, 10),
            'is_active' => true,
        ];
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
