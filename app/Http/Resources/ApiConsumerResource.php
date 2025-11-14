<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiConsumerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'website_url' => $this->website_url,
            'description' => $this->description,
            'api_key' => $this->when(
                $this->shouldShowApiKey($request),
                $this->api_key
            ),
            'allowed_origins' => $this->allowed_origins,
            'rate_limit' => $this->rate_limit,
            'is_active' => $this->is_active,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'creator' => $this->whenLoaded('creator', function () {
                return new UserMinimalResource($this->creator);
            }),
        ];
    }

    /**
     * Determine if API key should be shown in response
     */
    private function shouldShowApiKey(Request $request): bool
    {
        // Show API key only on create or regenerate actions
        return $request->routeIs('api-consumers.store')
            || $request->routeIs('api-consumers.regenerate-key');
    }
}
