<?php

namespace App\Contracts\Payment;

use App\Enums\PaymentCapability;

/**
 * Base contract every payment provider implementation must satisfy
 * (XenditService today, a future MidtransService, etc.).
 *
 * Feature-specific operations live on separate capability interfaces in this
 * namespace (ProvidesBalance, ProvidesTransactions, ProvidesPayouts). A
 * provider implements only the capabilities it actually supports; callers
 * check support via supports() or an instanceof check before invoking them.
 */
interface PaymentProvider
{
    /**
     * Machine name of the provider, matching ProjectPaymentGateway::$provider.
     */
    public function provider(): string;

    /**
     * Capabilities this provider instance supports.
     *
     * @return array<int, PaymentCapability>
     */
    public function capabilities(): array;

    public function supports(PaymentCapability $capability): bool;
}
