<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
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
            'description' => fake()->optional(0.7)->paragraph(),
            'company_name' => fake()->optional(0.8)->company(),
            'company_address' => fake()->optional(0.6)->address(),
            'company_email' => fake()->optional(0.7)->companyEmail(),
            'company_phone' => fake()->optional(0.5)->phoneNumber(),
            'status' => 'active',
            'visibility' => 'private',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }
}
