<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiConsumerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Never includes `api_key`/`api_key_hash` — the raw key is surfaced
     * exactly once, directly by the controller, on create/regenerate only
     * (see ApiConsumerController), never through this resource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'website_url' => $this->website_url,
            'description' => $this->description ?? null,
            'allowed_origins' => $this->allowed_origins,
            'rate_limit' => $this->rate_limit,
            'is_active' => $this->is_active,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'creator' => $this->whenLoaded('creator', function () {
                return new UserMinimalResource($this->creator);
            }),
            'projects' => $this->whenLoaded('projects', fn () => $this->projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'username' => $project->username,
                'profile_image' => $project->hasMedia('profile_image') ? $project->getMediaUrls('profile_image') : null,
            ])),
        ];
    }
}
