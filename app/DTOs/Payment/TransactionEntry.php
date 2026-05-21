<?php

namespace App\DTOs\Payment;

use Illuminate\Support\Carbon;

/**
 * A single normalized transaction row, shaped identically regardless of which
 * provider it came from.
 */
final readonly class TransactionEntry
{
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public ?string $channelCode,
        public ?string $channelCategory,
        public float $amount,
        public ?float $netAmount,
        public string $currency,
        public ?string $reference,
        public ?Carbon $createdAt,
        public ?string $settlementStatus,
        public ?Carbon $estimatedSettlementTime,
    ) {}
}
