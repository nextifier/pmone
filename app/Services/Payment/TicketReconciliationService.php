<?php

namespace App\Services\Payment;

use App\Contracts\Payment\ProvidesTransactions;
use App\DTOs\Payment\ReconciliationReport;
use App\DTOs\Payment\TicketReconciliationDiscrepancy;
use App\DTOs\Payment\TransactionEntry;
use App\DTOs\Payment\TransactionQuery;
use App\Models\TicketOrder;

/**
 * Reconciles a provider's successful payment transactions against PM One
 * ticket orders. This is the Hazard A safety net: a paid-after-expiry webhook
 * that could not safely resurrect an order (stock resold in the meantime)
 * leaves it Expired with `paid_after_expiry_at` set but otherwise unpaid from
 * PM One's point of view — this walks the gateway's own record of successful
 * payments so staff can spot any ticket order whose gateway payment succeeded
 * but the order itself never reached Confirmed, independent of whether a
 * webhook ever arrived at all.
 *
 * Sibling of PaymentReconciliationService (reservations), not an extension of
 * it: that service's public DTO (`ReconciliationDiscrepancy`) keys on
 * reservation-specific field names (`reservationNumber`, `reservationStatus`,
 * `reservationAmount`) that existing reservation-reconciliation tests assert
 * on directly. Overloading those fields for ticket orders would either rename
 * them (breaking the reservation contract) or blur what they mean, so this
 * mirrors the same algorithm against TicketOrder with its own discrepancy DTO
 * instead. The matching key is the transaction `reference_id`, which for
 * ticket orders equals the order number (the invoice external_id / order_id).
 */
class TicketReconciliationService
{
    /** Cursor pages to walk before declaring the report truncated. */
    private const MAX_PAGES = 40;

    private const PAGE_SIZE = 50;

    /** Tolerated rounding gap (IDR) when comparing amounts. */
    private const AMOUNT_EPSILON = 1.0;

    public function reconcile(
        ProvidesTransactions $provider,
        int $projectId,
        string $dateFrom,
        string $dateTo,
    ): ReconciliationReport {
        [$transactions, $truncated] = $this->fetchSuccessfulPayments($provider, $dateFrom, $dateTo);

        $references = collect($transactions)
            ->map(fn (TransactionEntry $txn): ?string => $txn->reference)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $orders = TicketOrder::query()
            ->whereHas('event', fn ($query) => $query->where('project_id', $projectId))
            ->whereIn('order_number', $references)
            ->get()
            ->keyBy('order_number');

        $matchedCount = 0;
        $matchedAmount = 0.0;
        $discrepancies = [];

        foreach ($transactions as $txn) {
            $order = $txn->reference !== null ? $orders->get($txn->reference) : null;

            if (! $order) {
                $discrepancies[] = new TicketReconciliationDiscrepancy(
                    type: 'orphan',
                    referenceId: (string) $txn->reference,
                    transactionId: $txn->id,
                    transactionAmount: $txn->amount,
                    transactionStatus: $txn->status,
                    orderNumber: null,
                    orderStatus: null,
                    orderAmount: null,
                    note: 'Successful payment with no matching ticket order in this project.',
                );

                continue;
            }

            if (! $order->isConfirmed()) {
                $discrepancies[] = new TicketReconciliationDiscrepancy(
                    type: 'status_mismatch',
                    referenceId: (string) $txn->reference,
                    transactionId: $txn->id,
                    transactionAmount: $txn->amount,
                    transactionStatus: $txn->status,
                    orderNumber: $order->order_number,
                    orderStatus: $order->status->value,
                    orderAmount: (float) $order->total,
                    note: 'Gateway shows a successful payment but the ticket order is not Confirmed.',
                );

                continue;
            }

            if (abs((float) $order->total - $txn->amount) > self::AMOUNT_EPSILON) {
                $discrepancies[] = new TicketReconciliationDiscrepancy(
                    type: 'amount_mismatch',
                    referenceId: (string) $txn->reference,
                    transactionId: $txn->id,
                    transactionAmount: $txn->amount,
                    transactionStatus: $txn->status,
                    orderNumber: $order->order_number,
                    orderStatus: $order->status->value,
                    orderAmount: (float) $order->total,
                    note: 'Paid amount differs from the ticket order total.',
                );

                continue;
            }

            $matchedCount++;
            $matchedAmount += $txn->amount;
        }

        return new ReconciliationReport(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            transactionCount: count($transactions),
            matchedCount: $matchedCount,
            matchedAmount: $matchedAmount,
            discrepancies: $discrepancies,
            truncated: $truncated,
        );
    }

    /**
     * Walk every cursor page of successful payments in the range.
     *
     * @return array{0: array<int, TransactionEntry>, 1: bool}
     */
    private function fetchSuccessfulPayments(
        ProvidesTransactions $provider,
        string $dateFrom,
        string $dateTo,
    ): array {
        $all = [];
        $cursor = null;

        for ($i = 0; $i < self::MAX_PAGES; $i++) {
            $page = $provider->listTransactions(new TransactionQuery(
                limit: self::PAGE_SIZE,
                afterId: $cursor,
                type: 'payment',
                status: 'success',
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ));

            $all = array_merge($all, $page->entries);

            if (! $page->hasMore || $page->nextCursor === null) {
                return [$all, false];
            }

            $cursor = $page->nextCursor;
        }

        return [$all, true];
    }
}
