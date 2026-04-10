<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website_url' => $this->website_url,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'order_column' => $this->order_column,
            'partner_logo' => $this->whenLoaded('media', fn () => $this->partner_logo),
            'events_count' => $this->when(
                isset($this->partner_categories_count),
                fn () => $this->partner_categories_count
            ),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'updater' => $this->whenLoaded('updater', fn () => [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
            ]),
            'deleter' => $this->whenLoaded('deleter', fn () => [
                'id' => $this->deleter->id,
                'name' => $this->deleter->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
