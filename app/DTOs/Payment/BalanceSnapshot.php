<?php

namespace App\DTOs\Payment;

use Illuminate\Support\Carbon;

/**
 * Provider-agnostic snapshot of an account balance at a point in time.
 * `available` is the primary spendable balance; `accounts` carries the
 * per-bucket breakdown when the provider exposes more than one.
 */
final readonly class BalanceSnapshot
{
    /**
     * @param  array<int, BalanceAccount>  $accounts
     */
    public function __construct(
        public float $available,
        public string $currency,
        public array $accounts,
        public Carbon $fetchedAt,
    ) {}
}
