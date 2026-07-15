<?php

namespace App\Http\Resources;

use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Support\FormFieldTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicBrandDetailResource extends PublicBrandIndexResource
{
    public function toArray(Request $request): array
    {
        $brand = $this->brand;

        return array_merge(parent::toArray($request), [
            'brand_description' => $brand?->description,
            'custom_fields' => $this->getPublicCustomFields(),
            'event_title' => $this->event?->title,
            'event_date_label' => $this->event?->date_label,
            'event_poster' => $this->getEventPoster(),
            'event_location' => $this->event?->location,
            'event_hall' => $this->event?->hall,
            'promotions' => $this->getFullPromotions(),
            'related_brands' => PublicBrandIndexResource::collection($this->getRelatedBrands())->resolve($request),
        ]);
    }

    /**
     * Event poster image, ensuring the event media relation is loaded so the
     * poster_image accessor can resolve without relying on controller eager loading.
     */
    private function getEventPoster(): ?array
    {
        $event = $this->event;

        if (! $event) {
            return null;
        }

        $event->loadMissing('media');

        return $event->poster_image;
    }

    private function getFullPromotions(): array
    {
        if (! $this->relationLoaded('promotionPosts')) {
            return [];
        }

        return $this->promotionPosts
            ->filter(fn ($post) => $post->relationLoaded('media') && $post->hasMedia('post_image'))
            ->map(fn ($post) => [
                'images' => $post->post_images,
                'caption' => $post->caption,
                'created_at' => $post->created_at?->toISOString(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Non-empty, public brand custom fields, in definition order, formatted per
     * field type (option labels, multi-value joins, etc.). Fields whose
     * settings.public is false are internal-only and excluded here. Returns a
     * list of {key, label, value} so the public page can render labels and the
     * admin live preview stays identical.
     *
     * @return array<int, array{key: string, label: string, value: string}>
     */
    private function getPublicCustomFields(): array
    {
        $values = $this->brand?->custom_fields;

        if (! is_array($values) || $values === []) {
            return [];
        }

        $event = $this->event;

        if (! $event) {
            return [];
        }

        $event->loadMissing('project');
        $project = $event->project;

        if (! $project) {
            return [];
        }

        return $project->customFields()
            ->get()
            ->filter(fn (CustomField $field) => $field->type !== CustomField::TYPE_SECTION
                && ($field->settings['public'] ?? true) !== false
                && filled($values[$field->key] ?? null))
            ->map(fn (CustomField $field) => [
                'key' => $field->key,
                'label' => (string) $field->label,
                'value' => FormFieldTypes::formatValue($field, $values[$field->key] ?? null),
            ])
            ->reject(fn (array $field) => $field['value'] === '' || $field['value'] === '-')
            ->values()
            ->toArray();
    }

    /**
     * Related brands at the same event, ranked by relevance: shared business
     * categories first, then same cluster/hall, then profile completeness.
     * Falls back to other brands at the event when there are too few matches.
     */
    private function getRelatedBrands(int $limit = 12): Collection
    {
        $brand = $this->brand;

        if (! $brand || ! $this->event_id) {
            return collect();
        }

        $eager = ['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media'];

        $categoryTagIds = $brand->relationLoaded('tags')
            ? $brand->tags
                ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
                ->pluck('id')
                ->all()
            : [];

        $baseQuery = fn () => BrandEvent::query()
            ->with($eager)
            ->where('event_id', $this->event_id)
            ->where('status', 'active')
            ->where('id', '!=', $this->id)
            ->whereHas('brand', fn ($q) => $q->where('status', 'active')->where('visibility', 'public'));

        // Candidates that share at least one business category.
        $candidates = collect();
        if (! empty($categoryTagIds)) {
            $candidates = $baseQuery()
                ->whereHas('brand.tags', fn ($q) => $q->whereIn('tags.id', $categoryTagIds))
                ->limit(40)
                ->get();
        }

        $categorySet = array_flip($categoryTagIds);
        $currentFields = $this->custom_fields ?? [];
        $currentCluster = $currentFields['cluster'] ?? null;
        $currentHall = $currentFields['hall'] ?? null;

        $ranked = $candidates
            ->map(function ($brandEvent) use ($categorySet, $currentCluster, $currentHall) {
                $related = $brandEvent->brand;
                $shared = $related && $related->relationLoaded('tags')
                    ? $related->tags->filter(fn ($tag) => isset($categorySet[$tag->id]))->count()
                    : 0;
                $fields = $brandEvent->custom_fields ?? [];
                $hasPromotions = $brandEvent->relationLoaded('promotionPosts')
                    && $brandEvent->promotionPosts->isNotEmpty();

                $score = $shared * 10
                    + (($currentCluster && ($fields['cluster'] ?? null) === $currentCluster) ? 3 : 0)
                    + (($currentHall && ($fields['hall'] ?? null) === $currentHall) ? 2 : 0)
                    + ($hasPromotions ? 1 : 0);

                return ['brand_event' => $brandEvent, 'score' => $score, 'order' => $brandEvent->order_column ?? PHP_INT_MAX];
            })
            ->sort(function ($a, $b) {
                return $b['score'] <=> $a['score'] ?: $a['order'] <=> $b['order'];
            })
            ->pluck('brand_event')
            ->take($limit)
            ->values();

        // Fill remaining slots with other brands at the same event.
        if ($ranked->count() < $limit) {
            $existingIds = $ranked->pluck('id')->all();
            $fillers = $baseQuery()
                ->when($existingIds, fn ($q) => $q->whereNotIn('id', $existingIds))
                ->orderBy('order_column')
                ->limit($limit - $ranked->count())
                ->get();

            $ranked = $ranked->concat($fillers);
        }

        return $ranked->values();
    }
}
