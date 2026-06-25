<?php

namespace App\Contracts\Payment;

use App\Support\PaymentChannels;

/**
 * Something a payment provider can open a hosted checkout for - a hotel
 * Reservation or a ticket TicketOrder. Exposing a uniform shape lets
 * XenditService/MidtransService create a checkout without knowing which product
 * (or which provider) backs it, mirroring how reservations already work.
 *
 * Customer values are returned RAW; provider-specific formatting (e.g. Xendit
 * Sessions' strict E.164 phone) is applied in the service, not here.
 */
interface CheckoutPayable
{
    /** Provider reference id used to correlate the payment (reservation_number / order_number). */
    public function checkoutReference(): string;

    /** Total to charge, whole rupiah. */
    public function checkoutAmount(): float;

    /** Human-readable description shown on the hosted checkout. */
    public function checkoutDescription(): string;

    /**
     * @return array{given_names: ?string, email: ?string, mobile_number: ?string}
     */
    public function checkoutCustomer(): array;

    /**
     * Canonical payment-channel codes the checkout is restricted to, or null to
     * accept every channel. See {@see PaymentChannels}.
     *
     * @return array<int, string>|null
     */
    public function allowedPaymentChannels(): ?array;
}
