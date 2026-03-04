<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'cover_image' => $this->getMediaUrls('cover_image'),
            'settings' => [
                'confirmation_message' => $this->settings['confirmation_message'] ?? null,
                'redirect_url' => $this->settings['redirect_url'] ?? null,
                'require_email' => $this->settings['require_email'] ?? false,
                'prevent_duplicate' => $this->settings['prevent_duplicate'] ?? false,
                'prevent_duplicate_by' => $this->settings['prevent_duplicate_by'] ?? 'fingerprint',
            ],
            'fields' => FormFieldResource::collection($this->whenLoaded('fields')),
            'status' => $this->status,
        ];
    }
}
