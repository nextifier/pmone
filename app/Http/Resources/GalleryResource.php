<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Admin resource for a single gallery photo (Spatie Media in the `gallery`
 * collection). Shape matches what GalleryManager expects: `sm` thumbnail, `xl`
 * lightbox, `lqip` placeholder, plus natural `width`/`height` for no-CLS layout.
 *
 * @mixin Media
 */
class GalleryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'caption' => $this->getCustomProperty('caption'),
            'order_column' => $this->order_column,
            'url' => $this->getUrl(),
            'lqip' => $this->hasGeneratedConversion('lqip') ? $this->getUrl('lqip') : null,
            'sm' => $this->hasGeneratedConversion('sm') ? $this->getUrl('sm') : $this->getUrl(),
            'xl' => $this->hasGeneratedConversion('xl') ? $this->getUrl('xl') : $this->getUrl(),
            'width' => $this->getCustomProperty('width'),
            'height' => $this->getCustomProperty('height'),
        ];
    }
}
