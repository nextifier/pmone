<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GaPropertyResource extends JsonResource
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
            'project_id' => $this->project_id,
            'name' => $this->name,
            'property_id' => $this->property_id,
            'is_active' => $this->is_active,
            'sync_frequency' => $this->sync_frequency,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'next_sync_at' => $this->next_sync_at?->toIso8601String(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // Relationships
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name')->map(function ($name) {
                    return is_array($name) ? ($name['en'] ?? reset($name)) : $name;
                })->values()->toArray();
            }),

            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'profile_image' => $this->project->getMediaUrls('profile_image'),
                ];
            }),

            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
        ];
    }
}
