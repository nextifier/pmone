<?php

namespace App\Enums;

/**
 * Discrete capabilities a payment provider may expose. A provider implements
 * only the capability interfaces it actually supports (see App\Contracts\Payment)
 * and reports the matching cases here, so the API and UI can adapt per provider
 * without hardcoding provider names.
 */
enum PaymentCapability: string
{
    case Balance = 'balance';
    case Transactions = 'transactions';
    case Settlement = 'settlement';
    case Payouts = 'payouts';
    case Invoicing = 'invoicing';
    case Refunds = 'refunds';
}
