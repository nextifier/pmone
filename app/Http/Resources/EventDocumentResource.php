<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'event_id' => $this->event_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'document_type' => $this->document_type,
            'is_required' => $this->is_required,
            'blocks_next_step' => $this->blocks_next_step,
            'submission_deadline' => $this->submission_deadline,
            'booth_types' => $this->booth_types,
            'settings' => $this->settings,
            'content_version' => $this->content_version,
            'content_updated_at' => $this->content_updated_at,
            'template_en' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('template_en'),
                fn () => $this->getMediaUrls('template_en')
            ),
            'template_id' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('template_id'),
                fn () => $this->getMediaUrls('template_id')
            ),
            'example_file' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('example_file'),
                fn () => $this->getMediaUrls('example_file')
            ),
            'description_images' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('description_images'),
                fn () => $this->getMediaUrls('description_images')
            ),
            'order_column' => $this->order_column,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
