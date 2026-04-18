<?php

namespace Database\Factories;

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
            'name' => $name,
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Jakarta', 'Bali', 'Bandung', 'Surabaya', 'Yogyakarta']),
            'country' => 'Indonesia',
            'latitude' => fake()->latitude(-8, -6),
            'longitude' => fake()->longitude(106, 115),
            'check_in_time' => '14:00:00',
            'check_out_time' => '12:00:00',
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
