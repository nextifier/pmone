<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaCoverageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'title' => $this->title,
            'url' => $this->url,
            'published_at' => $this->published_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
            'settings' => $this->settings ?? [],
            'can_edit' => $user ? $user->can('media_coverages.update') : false,
            'can_delete' => $user ? $user->can('media_coverages.delete') : false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'deleted_by_user' => $this->whenLoaded('deleter', fn () => $this->deleter ? [
                'id' => $this->deleter->id,
                'name' => $this->deleter->name,
            ] : null),
        ];
    }
}
