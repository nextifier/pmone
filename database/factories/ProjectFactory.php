<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'username' => fake()->unique()->slug(2),
            'bio' => fake()->optional()->paragraph(),
            'status' => 'active',
            'visibility' => 'public',
            'email' => fake()->companyEmail(),
            'hotel_reservation_enabled' => true,
        ];
    }

    /**
     * Factory state for projects with hotel reservation explicitly disabled.
     */
    public function withoutHotelReservation(): static
    {
        return $this->state(fn () => ['hotel_reservation_enabled' => false]);
    }
}
