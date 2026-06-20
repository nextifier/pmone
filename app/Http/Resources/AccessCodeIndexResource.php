<?php

namespace App\Http\Resources;

use App\Models\AccessCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact access code shape for the admin list table.
 *
 * @mixin AccessCode
 */
class AccessCodeIndexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'code' => $this->code,
            'kind' => $this->kind?->value,
            'status' => $this->status?->value,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'bind_email' => $this->bind_email,
            'bind_phone' => $this->bind_phone,
            'price_effect' => $this->price_effect?->value,
            'price_value' => $this->price_value !== null ? (float) $this->price_value : null,
            'stackable' => (bool) $this->stackable,
            'max_qty_per_redemption' => $this->max_qty_per_redemption,
            'batch' => $this->whenLoaded('batch', fn () => [
                'ulid' => $this->batch?->ulid,
                'name' => $this->batch?->name,
                'assigned_to' => $this->batch?->assigned_to,
            ]),
            'unlocks_count' => $this->whenCounted('unlocks'),
            'redemptions_count' => $this->whenCounted('redemptions'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
