<?php

namespace App\Services\Payment;

use App\Contracts\Payment\ProvidesTransactions;
use App\DTOs\Payment\ReconciliationDiscrepancy;
use App\DTOs\Payment\ReconciliationReport;
use App\DTOs\Payment\TransactionEntry;
use App\DTOs\Payment\TransactionQuery;
use App\Models\Reservation;

/**
 * Reconciles a provider's successful payment transactions against PM One
 * reservations. The matching key is the transaction `reference_id`, which for
 * invoice payments equals the reservation number (the invoice external_id).
 */
class PaymentReconciliationService
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

        $reservations = Reservation::query()
            ->whereHas('event', fn ($query) => $query->where('project_id', $projectId))
            ->whereIn('reservation_number', $references)
            ->get()
            ->keyBy('reservation_number');

        $matchedCount = 0;
        $matchedAmount = 0.0;
        $discrepancies = [];

        foreach ($transactions as $txn) {
            $reservation = $txn->reference !== null ? $reservations->get($txn->reference) : null;

            if (! $reservation) {
                $discrepancies[] = new ReconciliationDiscrepancy(
                    type: 'orphan',
                    referenceId: (string) $txn->reference,
                    transactionId: $txn->id,
                    transactionAmount: $txn->amount,
                    transactionStatus: $txn->status,
                    reservationNumber: null,
                    reservationStatus: null,
                    reservationAmount: null,
                    note: 'Successful payment with no matching reservation in this project.',
                );

                continue;
            }

            if (! $reservation->status->isPaid()) {
                $discrepancies[] = new ReconciliationDiscrepancy(
                    type: 'status_mismatch',
                    referenceId: (string) $txn->reference,
                    transactionId: $txn->id,
                    transactionAmount: $txn->amount,
                    transactionStatus: $txn->status,
                    reservationNumber: $reservation->reservation_number,
                    reservationStatus: $reservation->status->value,
                    reservationAmount: (float) $reservation->total_amount,
                    note: 'Xendit shows a successful payment but the reservation is not marked paid.',
                );

                continue;
            }

            if (abs((float) $reservation->total_amount - $txn->amount) > self::AMOUNT_EPSILON) {
                $discrepancies[] = new ReconciliationDiscrepancy(
                    type: 'amount_mismatch',
                    referenceId: (string) $txn->reference,
                    transactionId: $txn->id,
                    transactionAmount: $txn->amount,
                    transactionStatus: $txn->status,
                    reservationNumber: $reservation->reservation_number,
                    reservationStatus: $reservation->status->value,
                    reservationAmount: (float) $reservation->total_amount,
                    note: 'Paid amount differs from the reservation total.',
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
