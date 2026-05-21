<?php

namespace App\DTOs\Payment;

/**
 * A single mismatch found while reconciling provider transactions against
 * PM One reservations.
 */
final readonly class ReconciliationDiscrepancy
{
    public function __construct(
        public string $type,
        public string $referenceId,
        public string $transactionId,
        public float $transactionAmount,
        public string $transactionStatus,
        public ?string $reservationNumber,
        public ?string $reservationStatus,
        public ?float $reservationAmount,
        public string $note,
    ) {}
}
