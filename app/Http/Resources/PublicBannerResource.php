<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maps a ProjectBanner into the exact shape consumed by the event websites'
 * BannerHero.vue carousel. The component picks its render branch by the presence
 * of `adImage` (image banner) then `link`, falling back to the text branch
 * (`subHeadline`/`content`/`cta`). Keep keys branch-specific so a text banner
 * never carries an `adImage` and vice-versa.
 */
class PublicBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $image = $this->image; // getMediaUrls('image') | null

        $common = [
            'id' => $this->id,
            'startTime' => $this->start_time?->toISOString(),
            'endTime' => $this->end_time?->toISOString(),
            'aspectRatio' => $this->aspect_ratio,
        ];

        if ($this->type === 'image') {
            return [
                ...$common,
                'adImage' => [
                    'src' => $image['md'] ?? $image['original'] ?? null,
                    'srcFull' => $image['original'] ?? null,
                    'alt' => $this->title,
                    'caption' => data_get($this->settings, 'caption'),
                ],
                'link' => $this->link ?: null,
            ];
        }

        return [
            ...$common,
            'subHeadline' => $this->title,
            'content' => $this->description,
            'cta' => $this->cta_label
                ? ['label' => $this->cta_label, 'link' => $this->link]
                : null,
            'img' => ($this->type === 'image_text' && $image)
                ? [
                    'src' => $image['sm'] ?? $image['original'],
                    'w' => $image['width'],
                    'h' => $image['height'],
                ]
                : null,
        ];
    }
}
