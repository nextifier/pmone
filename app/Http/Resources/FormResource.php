<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'settings' => $this->settings,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_open' => $this->isOpen(),
            'opens_at' => $this->opens_at,
            'closes_at' => $this->closes_at,
            'response_limit' => $this->response_limit,
            'responses_count' => $this->whenCounted('responses'),
            'cover_image' => $this->getMediaUrls('cover_image'),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),

            'fields' => FormFieldResource::collection($this->whenLoaded('fields')),
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'ulid' => $this->project->ulid,
                'name' => $this->project->name,
                'username' => $this->project->username,
                'profile_image' => $this->project->hasMedia('profile_image')
                    ? $this->project->getMediaUrls('profile_image')
                    : null,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),

            'can_edit' => auth()->check() ? auth()->user()->can('update', $this->resource) : false,
            'can_delete' => auth()->check() ? auth()->user()->can('delete', $this->resource) : false,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
