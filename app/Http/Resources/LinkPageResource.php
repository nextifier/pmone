<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkPageResource extends JsonResource
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
            'more_details' => $this->more_details,
            'settings' => $this->settings,
            'order_column' => $this->order_column,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'og_type' => $this->og_type,
            'items_count' => $this->items_count ?? 0,
            'visits_count' => $this->visits_count ?? 0,
            'clicks_count' => $this->clicks_count ?? 0,
            'cover_image' => $this->cover_image,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
            ]),

            'items' => $this->whenLoaded('items', function () {
                return LinkPageItemResource::collection($this->items);
            }),
        ];
    }
}
