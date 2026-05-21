<?php

namespace Database\Factories;

use App\Models\PaymentWebhookEvent;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhookEvent>
 */
class PaymentWebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'xendit',
            'project_id' => Project::factory(),
            'event_type' => fake()->randomElement(['invoice.paid', 'invoice.expired', 'refund.succeeded']),
            'external_id' => 'HTL-'.fake()->numerify('########'),
            'status' => fake()->randomElement(['processed', 'ignored', 'rejected', 'error']),
            'http_status' => fake()->randomElement([200, 200, 401, 500]),
            'message' => fake()->sentence(4),
            'payload' => ['event' => 'invoice.paid', 'status' => 'PAID'],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
