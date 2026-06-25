<?php

namespace App\Contracts\Payment;

use App\Models\ProjectPaymentGateway;

/**
 * A payment provider that can create a hosted checkout for any CheckoutPayable
 * (a hotel Reservation or a ticket TicketOrder).
 *
 * Both XenditService (Invoices / Sessions) and MidtransService (Snap) implement
 * this, so callers create a payment without knowing which product or which
 * provider backs the project's active gateway. The return shape is intentionally
 * method-agnostic — `reference` is whatever id the provider uses to correlate
 * the payment (Xendit invoice/session id, Midtrans Snap token) and is stored on
 * the record's `xendit_invoice_id` column.
 */
interface CreatesCheckout
{
    /**
     * @return array{reference: string, payment_url: string, checkout_method: string}
     */
    public function createCheckout(
        CheckoutPayable $payable,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array;

    public function gateway(): ?ProjectPaymentGateway;
}
