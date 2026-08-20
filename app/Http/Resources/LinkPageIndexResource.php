<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkPageIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'visibility' => $this->visibility,
            'items_count' => $this->items_count ?? 0,
            // Views and clicks since the page was created, from the permanent daily
            // rollup. Prefer these over the two below.
            'lifetime_views' => (int) ($this->lifetime_views ?? 0),
            'lifetime_clicks' => (int) ($this->lifetime_clicks ?? 0),
            // @deprecated Rows inside the 90-day retention window. `clicks_count`
            // has always been zero besides: clicks land on the page's items, never
            // on the page.
            'visits_count' => $this->visits_count ?? 0,
            'clicks_count' => $this->clicks_count ?? 0,
            'cover_image' => $this->cover_image,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
            ]),

            'deleter' => $this->whenLoaded('deleter', fn () => [
                'id' => $this->deleter->id,
                'name' => $this->deleter->name,
                'username' => $this->deleter->username,
            ]),
        ];
    }
}
