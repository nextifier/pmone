<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\TransactionPage;
use App\DTOs\Payment\TransactionQuery;
use App\Exceptions\Payment\PaymentProviderException;

/**
 * Implemented by providers that expose a transaction history read API.
 */
interface ProvidesTransactions
{
    /**
     * List transactions matching the given filter, one cursor page at a time.
     *
     * @throws PaymentProviderException when the provider call fails.
     */
    public function listTransactions(TransactionQuery $query): TransactionPage;
}
