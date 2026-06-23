<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a Laravel Sanctum PersonalAccessToken. Never exposes the token hash.
 */
class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'last_used_human' => $this->last_used_at?->diffForHumans(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_expired' => $this->expires_at !== null && $this->expires_at->isPast(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
