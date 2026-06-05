<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProvider;
use App\Models\ProjectPaymentGateway;
use App\Services\Midtrans\MidtransService;
use App\Services\Xendit\XenditService;
use InvalidArgumentException;

/**
 * Builds a concrete PaymentProvider implementation bound to a project gateway.
 *
 * This is the single place where the `provider` string is matched to a class.
 * Adding Midtrans later means adding one match arm here plus the MidtransService
 * class — controllers, routes, DTOs, DB schema and the frontend stay untouched.
 */
class PaymentProviderFactory
{
    public function make(ProjectPaymentGateway $gateway): PaymentProvider
    {
        return match ($gateway->provider) {
            'xendit' => XenditService::forGateway($gateway),
            'midtrans' => MidtransService::forGateway($gateway),
            default => throw new InvalidArgumentException(
                "Unsupported payment provider: {$gateway->provider}"
            ),
        };
    }
}
