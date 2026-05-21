<?php

namespace App\DTOs\Payment;

/**
 * A single balance bucket reported by a payment provider (e.g. Xendit's
 * CASH / HOLDING / TAX accounts). Normalized so the API shape stays identical
 * across providers.
 */
final readonly class BalanceAccount
{
    public function __construct(
        public string $accountType,
        public float $balance,
        public string $currency,
    ) {}
}
