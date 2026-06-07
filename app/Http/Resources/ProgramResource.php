<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin resource — exposes ALL locale translations as objects so the form can edit per-locale.
 */
class ProgramResource extends JsonResource
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

            'title' => $this->getTranslations('title'),
            'description' => $this->getTranslations('description'),

            'icon' => $this->icon,
            'is_active' => (bool) $this->is_active,
            'order_column' => $this->order_column,
            'settings' => $this->settings ?? [],

            'image' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('image'),
                fn () => $this->getMediaUrls('image')
            ),

            'can_edit' => $user ? $user->can('programs.update') : false,
            'can_delete' => $user ? $user->can('programs.delete') : false,

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
