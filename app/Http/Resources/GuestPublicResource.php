<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Public resource — exposes resolved (single-locale) strings for translatable fields.
 * Locale resolved via app()->getLocale() set by the controller before transforming.
 */
class GuestPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'title' => $this->title,
            'bio' => $this->bio,
            'organization' => $this->organization,
            'is_featured' => (bool) $this->is_featured,
            'order_column' => $this->order_column,

            'appearance_date' => $this->formatAppearanceDate($this->more_details['appearance_date'] ?? null),
            'transparent_background' => $this->more_details['transparent_background'] ?? false,

            'profile_image' => $this->when(
                $this->relationLoaded('media') || $this->hasMedia('profile_image'),
                fn () => $this->getMediaUrls('profile_image')
            ),

            'tags' => $this->whenLoaded('tags', fn () => $this->tags
                ->where('type', 'guest_topic')
                ->pluck('name')
                ->values()
                ->all()
            ),

            'links' => $this->whenLoaded('links', fn () => $this->links->map(fn ($link) => [
                'label' => $link->label,
                'url' => $link->url,
            ])->values()->all()),
        ];
    }

    /**
     * Format a stored {start, end} date range into the {date, month} display
     * shape the event websites expect (e.g. "25-26" / "Oct", or "25" / "Oct").
     *
     * @param  array{start?: string, end?: string}|null  $range
     * @return array{date: string, month: string}|null
     */
    private function formatAppearanceDate(?array $range): ?array
    {
        if (! $range || empty($range['start'])) {
            return null;
        }

        $start = Carbon::parse($range['start']);
        $end = ! empty($range['end']) ? Carbon::parse($range['end']) : $start;

        if ($start->isSameDay($end)) {
            return ['date' => $start->format('j'), 'month' => $start->format('M')];
        }

        if ($start->isSameMonth($end)) {
            return ['date' => $start->format('j').'-'.$end->format('j'), 'month' => $start->format('M')];
        }

        return [
            'date' => $start->format('j').' '.$start->format('M').' - '.$end->format('j').' '.$end->format('M'),
            'month' => '',
        ];
    }
}
