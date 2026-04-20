<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Hotel';

        return [
            'event_id' => Event::factory(),
            'name' => $name,
            'description' => fake()->paragraph(),
            'star_rating' => fake()->numberBetween(3, 5),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Jakarta', 'Bali', 'Bandung', 'Surabaya', 'Yogyakarta']),
            'country' => 'Indonesia',
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'commission_rate' => fake()->randomFloat(2, 5, 15),
            'tax_percentage' => 11.00,
            'service_charge_percentage' => fake()->randomElement([0, 5, 10]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
