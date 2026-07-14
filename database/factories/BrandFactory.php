<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
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
            'address' => fake()->boolean(60) ? [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'province' => '',
                'country' => 'Indonesia',
            ] : null,
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

    /**
     * Force the brand's address country (drives currency resolution).
     */
    public function country(?string $country): static
    {
        return $this->state(fn (array $attributes) => [
            'address' => array_merge(is_array($attributes['address'] ?? null) ? $attributes['address'] : [], [
                'country' => $country,
            ]),
        ]);
    }
}
