<?php

namespace Database\Factories;

use App\Models\ApiConsumer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiConsumerRequest>
 */
class ApiConsumerRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoints = [
            '/api/v1/news',
            '/api/v1/news/{id}',
            '/api/v1/categories',
            '/api/v1/posts',
            '/api/v1/posts/{slug}',
        ];

        $methods = ['GET', 'POST', 'PUT', 'DELETE'];

        return [
            'api_consumer_id' => ApiConsumer::factory(),
            'endpoint' => fake()->randomElement($endpoints),
            'method' => fake()->randomElement($methods),
            'status_code' => fake()->randomElement([200, 200, 200, 201, 400, 404, 500]),
            'response_time_ms' => fake()->numberBetween(10, 500),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'origin' => fake()->url(),
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the request was successful (2xx)
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => fake()->randomElement([200, 201, 204]),
        ]);
    }

    /**
     * Indicate that the request failed (4xx)
     */
    public function clientError(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => fake()->randomElement([400, 401, 403, 404, 422]),
        ]);
    }

    /**
     * Indicate that the request had server error (5xx)
     */
    public function serverError(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => fake()->randomElement([500, 502, 503]),
        ]);
    }

    /**
     * Set the request to be created today
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('today', 'now'),
        ]);
    }

    /**
     * Set a specific response time
     */
    public function withResponseTime(int $ms): static
    {
        return $this->state(fn (array $attributes) => [
            'response_time_ms' => $ms,
        ]);
    }
}
