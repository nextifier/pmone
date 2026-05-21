<?php

namespace App\Http\Resources\Payment;

use App\DTOs\Payment\SettlementBucket;
use App\DTOs\Payment\SettlementSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SettlementSummary
 */
class SettlementSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pending_amount' => $this->pendingAmount,
            'pending_count' => $this->pendingCount,
            'settled_amount' => $this->settledAmount,
            'settled_count' => $this->settledCount,
            'currency' => $this->currency,
            'truncated' => $this->truncated,
            'upcoming' => array_map(fn (SettlementBucket $bucket): array => [
                'date' => $bucket->date,
                'amount' => $bucket->amount,
                'count' => $bucket->count,
            ], $this->upcoming),
        ];
    }
}
