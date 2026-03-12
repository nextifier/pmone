<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'job_title' => fake()->optional(0.7)->jobTitle(),
            'emails' => [fake()->safeEmail()],
            'phones' => [fake()->phoneNumber()],
            'company_name' => fake()->optional(0.8)->company(),
            'website' => fake()->optional(0.5)->url(),
            'address' => fake()->optional(0.6)->randomElement([
                [
                    'street' => fake()->streetAddress(),
                    'city' => fake()->city(),
                    'province' => fake()->state(),
                    'postal_code' => fake()->postcode(),
                    'country' => 'Indonesia',
                ],
            ]),
            'notes' => fake()->optional(0.3)->sentence(),
            'source' => fake()->optional(0.7)->randomElement(['event', 'referral', 'website', 'import', 'manual']),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }
}
