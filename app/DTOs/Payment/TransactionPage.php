<?php

namespace App\DTOs\Payment;

/**
 * One page of transactions plus the cursor needed to fetch the next page.
 * `nextCursor` is null when there are no more results.
 */
final readonly class TransactionPage
{
    /**
     * @param  array<int, TransactionEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public bool $hasMore,
        public ?string $nextCursor,
    ) {}
}
