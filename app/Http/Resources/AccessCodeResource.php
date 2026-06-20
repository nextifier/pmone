<?php

namespace App\Http\Resources;

use App\Models\AccessCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full access code shape for the admin detail/edit view.
 *
 * @mixin AccessCode
 */
class AccessCodeResource extends JsonResource
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
            'event_id' => $this->event_id,
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
            'metadata' => $this->metadata,
            'batch' => $this->whenLoaded('batch', fn () => new AccessCodeBatchResource($this->batch)),
            'unlocks' => $this->whenLoaded('unlocks', fn () => $this->unlocks->map(fn ($t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'title' => $t->getTranslation('title', app()->getLocale(), false),
            ])),
            'redemptions_count' => $this->whenCounted('redemptions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
