<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin resource — exposes ALL locale translations as objects so the form can edit per-locale.
 */
class RundownItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'date' => $this->date?->format('Y-m-d'),
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),

            'title' => $this->getTranslations('title'),
            'subtitle' => $this->getTranslations('subtitle'),
            'description' => $this->getTranslations('description'),
            'theme' => $this->getTranslations('theme'),
            'location' => $this->getTranslations('location'),
            'presented_by' => $this->getTranslations('presented_by'),
            'moderator' => $this->getTranslations('moderator'),

            'panelists' => $this->panelists ?? [],
            'speakers' => $this->stripSpeakerAvatars($this->speakers),

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
            'more_details' => $this->more_details ?? [],

            'order_column' => $this->order_column,
            'is_active' => $this->is_active,

            'deleted_at' => $this->deleted_at,
            'deleted_by_user' => $this->whenLoaded('deleter', fn () => $this->deleter ? [
                'id' => $this->deleter->id,
                'name' => $this->deleter->name,
            ] : null),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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
     * Drop legacy avatar_url field from speakers payload.
     *
     * @param  mixed  $speakers
     * @return array<int|string, mixed>
     */
    private function stripSpeakerAvatars($speakers): array
    {
        if (! is_array($speakers)) {
            return [];
        }

        $strip = function (array $list): array {
            return array_map(function ($item) {
                if (is_array($item)) {
                    unset($item['avatar_url']);
                }

                return $item;
            }, $list);
        };

        if (isset($speakers['en']) || isset($speakers['id'])) {
            $out = [];
            foreach ($speakers as $locale => $list) {
                $out[$locale] = is_array($list) ? $strip($list) : $list;
            }

            return $out;
        }

        return $strip($speakers);
    }
}
