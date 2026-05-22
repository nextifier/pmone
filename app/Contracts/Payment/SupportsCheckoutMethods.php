<?php

namespace App\Contracts\Payment;

use App\Enums\Payment\CheckoutMethod;

/**
 * Implemented by providers that offer more than one checkout integration
 * (e.g. Xendit's Sessions API vs the legacy Invoices API).
 *
 * Optional capability interface — like ProvidesBalance — so the base
 * PaymentProvider contract stays minimal and a provider that has only one
 * checkout style is not forced to implement this.
 */
interface SupportsCheckoutMethods
{
    /**
     * Checkout methods this provider exposes, in display order. May include
     * methods that are not yet available (CheckoutMethod::available() === false)
     * so the UI can show them disabled.
     *
     * @return array<int, CheckoutMethod>
     */
    public function checkoutMethods(): array;

    /**
     * Whether the provider can actually create a payment with this method.
     */
    public function supportsCheckoutMethod(CheckoutMethod $method): bool;
}
