<?php

namespace App\Http\Resources;

use App\Models\AccessCodeBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccessCodeBatch
 */
class AccessCodeBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'kind' => $this->kind?->value,
            'assigned_to' => $this->assigned_to,
            'brand_id' => $this->brand_id,
            'notes' => $this->notes,
            'codes_count' => $this->whenCounted('accessCodes'),
            'access_codes' => AccessCodeIndexResource::collection($this->whenLoaded('accessCodes')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
