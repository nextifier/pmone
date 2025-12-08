<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
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
        ];
    }
}
