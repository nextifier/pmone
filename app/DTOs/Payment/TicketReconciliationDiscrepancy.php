<?php

namespace App\DTOs\Payment;

/**
 * A single mismatch found while reconciling provider transactions against
 * PM One ticket orders. Sibling of ReconciliationDiscrepancy (reservations) —
 * see TicketReconciliationService for why this is a separate DTO rather than
 * an extension of the reservation one.
 */
final readonly class TicketReconciliationDiscrepancy
{
    public function __construct(
        public string $type,
        public string $referenceId,
        public string $transactionId,
        public float $transactionAmount,
        public string $transactionStatus,
        public ?string $orderNumber,
        public ?string $orderStatus,
        public ?float $orderAmount,
        public string $note,
    ) {}
}
