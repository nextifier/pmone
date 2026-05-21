<?php

namespace App\Http\Resources\Payment;

use App\DTOs\Payment\ReconciliationDiscrepancy;
use App\DTOs\Payment\ReconciliationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReconciliationReport
 */
class ReconciliationReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'transaction_count' => $this->transactionCount,
            'matched_count' => $this->matchedCount,
            'matched_amount' => $this->matchedAmount,
            'discrepancy_count' => count($this->discrepancies),
            'truncated' => $this->truncated,
            'discrepancies' => array_map(fn (ReconciliationDiscrepancy $d): array => [
                'type' => $d->type,
                'reference_id' => $d->referenceId,
                'transaction_id' => $d->transactionId,
                'transaction_amount' => $d->transactionAmount,
                'transaction_status' => $d->transactionStatus,
                'reservation_number' => $d->reservationNumber,
                'reservation_status' => $d->reservationStatus,
                'reservation_amount' => $d->reservationAmount,
                'note' => $d->note,
            ], $this->discrepancies),
        ];
    }
}
