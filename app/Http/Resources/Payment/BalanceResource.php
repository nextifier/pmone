<?php

namespace App\Http\Resources\Payment;

use App\DTOs\Payment\BalanceAccount;
use App\DTOs\Payment\BalanceSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BalanceSnapshot
 */
class BalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available' => $this->available,
            'currency' => $this->currency,
            'accounts' => array_map(fn (BalanceAccount $account): array => [
                'account_type' => $account->accountType,
                'balance' => $account->balance,
                'currency' => $account->currency,
            ], $this->accounts),
            'fetched_at' => $this->fetchedAt->toIso8601String(),
        ];
    }
}
