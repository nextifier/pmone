<?php

namespace App\DTOs\Payment;

/**
 * Provider-agnostic settlement snapshot: how much of the money already
 * collected is still pending transfer to the merchant bank, how much has
 * settled, and when the pending amounts are expected to land.
 */
final readonly class SettlementSummary
{
    /**
     * @param  array<int, SettlementBucket>  $upcoming
     */
    public function __construct(
        public float $pendingAmount,
        public int $pendingCount,
        public float $settledAmount,
        public int $settledCount,
        public string $currency,
        public array $upcoming,
        public bool $truncated,
    ) {}
}
