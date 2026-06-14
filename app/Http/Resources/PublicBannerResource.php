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
                    'srcset' => $this->buildImageSrcset(),
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
            'subtitle' => data_get($this->settings, 'subtitle'),
            'accentColor' => data_get($this->settings, 'accentColor'),
            'content' => $this->description,
            'cta' => $this->cta_label
                ? ['label' => $this->cta_label, 'link' => $this->link]
                : null,
            'img' => ($this->type === 'image_text' && $image)
                ? [
                    'src' => $image['sm'] ?? $image['original'],
                    'srcset' => $this->buildImageSrcset(),
                    'w' => $image['width'],
                    'h' => $image['height'],
                ]
                : null,
        ];
    }

    /**
     * Native <img srcset> built straight from the generated conversions (no
     * NuxtImg/CDN re-transform). Only generated conversions are included so the
     * srcset never carries duplicate URLs during the conversion queue window.
     */
    private function buildImageSrcset(): ?string
    {
        $media = $this->getFirstMedia('image');
        if (! $media) {
            return null;
        }

        $widths = ['sm' => 600, 'md' => 1200, 'lg' => 1440, 'xl' => 1600];
        $parts = [];
        foreach ($widths as $conversion => $width) {
            if ($media->hasGeneratedConversion($conversion)) {
                $parts[] = $media->getUrl($conversion).' '.$width.'w';
            }
        }
        $parts[] = $media->getUrl().' '.((int) ($media->getCustomProperty('width') ?: 1920)).'w';

        return implode(', ', $parts);
    }
}
