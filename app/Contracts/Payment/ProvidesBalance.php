<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\BalanceSnapshot;
use App\Exceptions\Payment\PaymentProviderException;

/**
 * Implemented by providers that expose an account balance read API.
 */
interface ProvidesBalance
{
    /**
     * Fetch the current account balance from the provider.
     *
     * @throws PaymentProviderException when the provider call fails.
     */
    public function getBalance(): BalanceSnapshot;
}
