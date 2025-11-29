<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiConsumer>
 */
class ApiConsumerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domains = [
            'example.com',
            'blog.example.com',
            'news.example.com',
            'magazine.example.org',
            'portal.example.net',
        ];

        $domain = fake()->randomElement($domains);
        $protocol = fake()->randomElement(['https', 'http']);
        $websiteUrl = "{$protocol}://{$domain}";

        return [
            'name' => fake()->company().' Website',
            'website_url' => $websiteUrl,
            'description' => fake()->sentence(10),
            'allowed_origins' => [
                $websiteUrl,
                str_replace('://', '://www.', $websiteUrl),
            ],
            'rate_limit' => fake()->randomElement([60, 100, 120, 150, 200]),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the API consumer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the API consumer has been used recently.
     */
    public function recentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the API consumer has not been used.
     */
    public function neverUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => null,
        ]);
    }

    /**
     * Indicate that the API consumer has high rate limit.
     */
    public function highRateLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_limit' => fake()->numberBetween(500, 1000),
        ]);
    }

    /**
     * Indicate that the API consumer has low rate limit.
     */
    public function lowRateLimit(): static
    {
        return $this->state(fn (array $attributes) => [
            'rate_limit' => fake()->numberBetween(10, 50),
        ]);
    }

    /**
     * Indicate that the API consumer has no origin restrictions.
     */
    public function unrestricted(): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_origins' => null,
        ]);
    }

    /**
     * Indicate that the API consumer has strict origin restrictions.
     */
    public function strictOrigins(): static
    {
        $domain = parse_url($attributes['website_url'] ?? 'https://example.com', PHP_URL_HOST);
        $protocol = parse_url($attributes['website_url'] ?? 'https://example.com', PHP_URL_SCHEME);

        return $this->state(fn (array $attributes) => [
            'allowed_origins' => [
                "{$protocol}://{$domain}",
            ],
        ]);
    }
}
