<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShortLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'destination_url' => $this->destination_url,
            'is_active' => $this->is_active,
            'clicks_count' => $this->clicks_count ?? 0,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'og_type' => $this->og_type,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // User relationship
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
            ]),

            // Recent clicks
            'recent_clicks' => $this->whenLoaded('clicks', function () {
                return $this->clicks->take(10)->map(function ($click) {
                    return [
                        'id' => $click->id,
                        'clicked_at' => $click->clicked_at->toISOString(),
                        'ip_address' => $click->ip_address,
                        'user_agent' => $click->user_agent,
                        'referer' => $click->referer,
                        'clicker' => $click->clicker ? [
                            'id' => $click->clicker->id,
                            'name' => $click->clicker->name,
                            'username' => $click->clicker->username,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
