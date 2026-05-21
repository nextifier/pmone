<?php

namespace App\DTOs\Payment;

/**
 * Result of reconciling a provider's successful payments against PM One
 * reservations for a date range.
 */
final readonly class ReconciliationReport
{
    /**
     * @param  array<int, ReconciliationDiscrepancy>  $discrepancies
     */
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public int $transactionCount,
        public int $matchedCount,
        public float $matchedAmount,
        public array $discrepancies,
        public bool $truncated,
    ) {}
}
