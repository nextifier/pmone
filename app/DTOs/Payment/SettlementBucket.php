<?php

namespace App\DTOs\Payment;

/**
 * Total amount expected to settle to the merchant bank on a given date.
 * `date` is null for pending payments with no estimated settlement time.
 */
final readonly class SettlementBucket
{
    public function __construct(
        public ?string $date,
        public float $amount,
        public int $count,
    ) {}
}
