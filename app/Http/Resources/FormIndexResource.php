<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_open' => $this->isOpen(),
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'response_limit' => $this->response_limit,
            'responses_count' => $this->responses_count ?? 0,
            'cover_image' => $this->getMediaUrls('cover_image'),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'username' => $this->project->username,
                'profile_image' => $this->project->hasMedia('profile_image')
                    ? $this->project->getMediaUrls('profile_image')
                    : null,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'can_edit' => auth()->check() ? auth()->user()->can('update', $this->resource) : false,
            'can_delete' => auth()->check() ? auth()->user()->can('delete', $this->resource) : false,
            'created_at' => $this->created_at,
        ];
    }
}
