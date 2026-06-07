<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public resource — shape mirrors what the pmone-events `MediaCard` expects
 * ({title, link, created_at}) so the website component needs no changes.
 */
class MediaCoveragePublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'link' => $this->url,
            'created_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
