<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Public resource for a single gallery photo. Shape mirrors what the
 * pmone-events `Gallery.vue` / Lightbox expects: `sm` thumbnail, `xl` (also
 * `url`) full image, `lqip` placeholder, `alt`, and natural `width`/`height`.
 *
 * @mixin Media
 */
class GalleryPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $full = $this->hasGeneratedConversion('xl') ? $this->getUrl('xl') : $this->getUrl();

        return [
            'id' => $this->id,
            'lqip' => $this->hasGeneratedConversion('lqip') ? $this->getUrl('lqip') : null,
            'sm' => $this->hasGeneratedConversion('sm') ? $this->getUrl('sm') : $this->getUrl(),
            'xl' => $full,
            'url' => $full,
            'alt' => $this->getCustomProperty('alt') ?? $this->name,
            'width' => $this->getCustomProperty('width'),
            'height' => $this->getCustomProperty('height'),
        ];
    }
}
