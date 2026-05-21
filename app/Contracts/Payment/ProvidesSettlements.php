<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\SettlementSummary;
use App\Exceptions\Payment\PaymentProviderException;

/**
 * Implemented by providers that can report settlement progress - how much
 * collected money is still pending transfer to the merchant bank.
 */
interface ProvidesSettlements
{
    /**
     * Summarize settlement status for payments in the given date range.
     *
     * @throws PaymentProviderException when the provider call fails.
     */
    public function getSettlementSummary(string $dateFrom, string $dateTo): SettlementSummary;
}
