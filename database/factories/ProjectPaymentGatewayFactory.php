<?php

namespace Database\Factories;

use App\Enums\Payment\CheckoutMethod;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPaymentGateway>
 */
class ProjectPaymentGatewayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'provider' => 'xendit',
            'label' => fake()->randomElement(['Production', 'Sandbox', null]),
            'mode' => fake()->randomElement(['live', 'test']),
            // Mirrors the column default so existing tests keep the legacy
            // Invoices flow. Use the states below to opt into Sessions.
            'checkout_method' => CheckoutMethod::PaymentLinkLegacy,
            'is_active' => true,
            'secret_key' => 'xnd_'.fake()->regexify('[A-Za-z0-9]{40}'),
            'public_key' => null,
            'webhook_token' => fake()->regexify('[A-Za-z0-9]{32}'),
            'config' => ['currency' => 'IDR'],
        ];
    }

    public function default(): self
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    public function sessionsPaymentLink(): self
    {
        return $this->state(['checkout_method' => CheckoutMethod::PaymentLinkSessions]);
    }

    public function paymentLinkLegacy(): self
    {
        return $this->state(['checkout_method' => CheckoutMethod::PaymentLinkLegacy]);
    }

    /**
     * A Midtrans gateway. Server/Client keys use the sandbox prefixes so
     * isConfigured() passes (it requires the "Mid-server-" substring). Midtrans
     * has no separate webhook token — signatures are verified with the Server
     * Key — so webhook_token is null.
     */
    public function midtrans(): self
    {
        return $this->state([
            'provider' => 'midtrans',
            'mode' => 'test',
            'secret_key' => 'SB-Mid-server-'.fake()->regexify('[A-Za-z0-9]{24}'),
            'public_key' => 'SB-Mid-client-'.fake()->regexify('[A-Za-z0-9]{24}'),
            'webhook_token' => null,
            'checkout_method' => CheckoutMethod::PaymentLinkLegacy,
            'config' => ['currency' => 'IDR'],
        ]);
    }
}
