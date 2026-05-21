<?php

namespace App\Http\Resources\Payment;

use App\DTOs\Payment\TransactionEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransactionEntry
 */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'channel_code' => $this->channelCode,
            'channel_category' => $this->channelCategory,
            'amount' => $this->amount,
            'net_amount' => $this->netAmount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'settlement_status' => $this->settlementStatus,
            'estimated_settlement_time' => $this->estimatedSettlementTime?->toIso8601String(),
            'created_at' => $this->createdAt?->toIso8601String(),
        ];
    }
}
