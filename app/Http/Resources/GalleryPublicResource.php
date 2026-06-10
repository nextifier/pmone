<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Public resource for a single gallery photo. Shape mirrors what the
 * pmone-events `Gallery.vue` / Lightbox expects: `sm` thumbnail, `xl` (also
 * `url`) full image, `lqip` placeholder, `alt`, and natural `width`/`height`.
 * The gallery only generates `lqip`/`sm` conversions, so the full image (`xl`)
 * is the stored original.
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
        $full = $this->getUrl();

        $caption = $this->getCustomProperty('caption');

        return [
            'id' => $this->id,
            'lqip' => $this->hasGeneratedConversion('lqip') ? $this->getUrl('lqip') : null,
            'sm' => $this->hasGeneratedConversion('sm') ? $this->getUrl('sm') : $this->getUrl(),
            'xl' => $full,
            'url' => $full,
            'caption' => $caption,
            'alt' => $caption ?? $this->getCustomProperty('alt') ?? $this->name,
            'width' => $this->getCustomProperty('width'),
            'height' => $this->getCustomProperty('height'),
        ];
    }
}
