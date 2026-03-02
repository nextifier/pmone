<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
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
            'ulid' => $this->ulid,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'edition_number' => $this->edition_number,
            'edition_number_with_ordinal' => $this->edition_number_with_ordinal,
            'description' => $this->description,
            'date_label' => $this->date_label,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'location' => $this->location,
            'location_link' => $this->location_link,
            'hall' => $this->hall,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'settings' => $this->settings,
            'custom_fields' => $this->custom_fields,
            'order_form_content' => $this->order_form_content,
            'order_form_deadline' => $this->order_form_deadline?->toIso8601String(),
            'promotion_post_deadline' => $this->promotion_post_deadline?->toIso8601String(),
            'gross_area' => $this->gross_area,
            'is_active' => $this->is_active,
            'order_column' => $this->order_column,
            'poster_image' => $this->when(
                $this->hasMedia('poster_image'),
                fn () => $this->getMediaUrls('poster_image')
            ),
            'creator' => $this->whenLoaded('creator', fn () => new UserMinimalResource($this->creator)),
            'updater' => $this->whenLoaded('updater', fn () => new UserMinimalResource($this->updater)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
