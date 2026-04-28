<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public resource — exposes resolved (single-locale) strings for translatable fields.
 * Locale is resolved via app()->getLocale() set by the controller before transforming.
 */
class RundownItemPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),

            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'theme' => $this->theme,
            'location' => $this->location,
            'presented_by' => $this->presented_by,
            'moderator' => $this->moderator,

            'panelists' => $this->localizedJsonArray($this->panelists),
            'speakers' => $this->stripAvatarUrls($this->localizedJsonArray($this->speakers)),

            'categories' => $this->whenLoaded('tags', fn () => $this->tags
                ->where('type', 'rundown_category')
                ->pluck('name')
                ->values()
                ->all()
            ),

            'poster_image' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('poster'),
                fn () => $this->getMediaUrls('poster')
            ),

            'settings' => $this->settings ?? [],
        ];
    }

    private function formatTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return substr($time, 0, 5);
    }

    /**
     * Resolve a JSONB array that might be either a flat list or per-locale shape.
     * Per-locale shape: ['en' => [...], 'id' => [...]]
     * Flat shape (legacy): [item1, item2, ...]
     */
    private function localizedJsonArray($value): array
    {
        if (! $value) {
            return [];
        }

        if (is_array($value) && (isset($value['en']) || isset($value['id']))) {
            $locale = app()->getLocale();
            $fallback = config('app.fallback_locale', 'en');

            return $value[$locale] ?? $value[$fallback] ?? $value['en'] ?? [];
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $speakers
     * @return array<int, mixed>
     */
    private function stripAvatarUrls(array $speakers): array
    {
        return array_map(function ($speaker) {
            if (is_array($speaker)) {
                unset($speaker['avatar_url']);
            }

            return $speaker;
        }, $speakers);
    }
}
