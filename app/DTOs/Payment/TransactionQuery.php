<?php

namespace App\DTOs\Payment;

/**
 * Provider-agnostic filter for a transaction listing request.
 *
 * `dateFrom` / `dateTo` are plain `Y-m-d` strings; each provider converts them
 * to whatever timestamp format its API expects. Pagination is cursor-based:
 * pass the previous page's TransactionPage::$nextCursor as `afterId`.
 */
final readonly class TransactionQuery
{
    public function __construct(
        public int $limit = 15,
        public ?string $afterId = null,
        public ?string $type = null,
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}
}
