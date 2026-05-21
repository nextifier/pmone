<?php

namespace App\Services\Payment;

/**
 * Single source of truth for the per-gateway balance cache key and TTL.
 *
 * Provider-agnostic: keyed by the ProjectPaymentGateway id (not the provider),
 * so the controller writes and the ProjectPaymentGatewayObserver invalidates
 * the exact same entry when credentials change.
 */
class PaymentBalanceCache
{
    /** Cache TTL for a balance snapshot, in seconds (5 minutes). */
    public const TTL = 300;

    public const PREFIX = 'payment:balance:gateway:';

    public static function key(int $gatewayId): string
    {
        return self::PREFIX.$gatewayId;
    }
}
