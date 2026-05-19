<?php

namespace App\Services\Payment;

use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use RuntimeException;

class PaymentGatewayResolver
{
    /**
     * Resolve the active payment gateway config for a project.
     *
     * Returns the default active gateway for the requested provider/mode.
     * Throws when no active gateway is configured.
     */
    public function resolve(Project $project, string $provider = 'xendit', string $mode = 'live'): ProjectPaymentGateway
    {
        $gateway = $project->defaultPaymentGateway($provider, $mode);

        if (! $gateway) {
            throw new RuntimeException(sprintf(
                'No active %s gateway (mode: %s) configured for project "%s". Configure one under project payment settings.',
                $provider,
                $mode,
                $project->username,
            ));
        }

        return $gateway;
    }

    /**
     * Resolve a gateway by webhook callback token (for inbound webhook routing).
     */
    public function resolveByWebhookToken(Project $project, string $provider, string $token): ?ProjectPaymentGateway
    {
        return $project->paymentGateways()
            ->active()
            ->forProvider($provider)
            ->get()
            ->first(fn (ProjectPaymentGateway $g) => $g->webhook_token !== null
                && hash_equals((string) $g->webhook_token, $token)
            );
    }
}
