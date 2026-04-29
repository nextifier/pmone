<?php

namespace App\Exports;

use App\Models\RundownItem;
use Illuminate\Database\Eloquent\Builder;

class RundownItemsExport extends BaseExport
{
    public function __construct(
        protected int $eventId,
        ?array $filters = null,
        ?string $sort = null,
    ) {
        parent::__construct($filters, $sort);
    }

    protected function getQuery(): Builder
    {
        return RundownItem::query()
            ->with(['tags'])
            ->where('event_id', $this->eventId)
            ->orderBy('date')
            ->orderBy('order_column');
    }

    public function headings(): array
    {
        return [
            'Date',
            'Start Time',
            'End Time',
            'Title (EN)',
            'Title (ID)',
            'Subtitle (EN)',
            'Subtitle (ID)',
            'Description (EN)',
            'Description (ID)',
            'Theme (EN)',
            'Theme (ID)',
            'Location (EN)',
            'Location (ID)',
            'Presented By (EN)',
            'Presented By (ID)',
            'Moderator (EN)',
            'Moderator (ID)',
            'Panelists (JSON)',
            'Speakers (JSON)',
            'Categories',
            'Active',
        ];
    }

    /**
     * @param  RundownItem  $item
     */
    public function map($item): array
    {
        $categories = $item->tags
            ->filter(fn ($tag) => $tag->type === 'rundown_category')
            ->pluck('name')
            ->unique()
            ->join(', ');

        $panelists = $this->flattenLocalizedList($item->panelists);
        $speakers = $this->flattenLocalizedList($item->speakers);

        return [
            $item->date?->format('Y-m-d'),
            $item->start_time,
            $item->end_time,
            $this->translation($item, 'title', 'en'),
            $this->translation($item, 'title', 'id'),
            $this->translation($item, 'subtitle', 'en'),
            $this->translation($item, 'subtitle', 'id'),
            $this->translation($item, 'description', 'en'),
            $this->translation($item, 'description', 'id'),
            $this->translation($item, 'theme', 'en'),
            $this->translation($item, 'theme', 'id'),
            $this->translation($item, 'location', 'en'),
            $this->translation($item, 'location', 'id'),
            $this->translation($item, 'presented_by', 'en'),
            $this->translation($item, 'presented_by', 'id'),
            $this->translation($item, 'moderator', 'en'),
            $this->translation($item, 'moderator', 'id'),
            ! empty($panelists) ? json_encode($panelists, JSON_UNESCAPED_UNICODE) : null,
            ! empty($speakers) ? json_encode($speakers, JSON_UNESCAPED_UNICODE) : null,
            $categories ?: null,
            $item->is_active ? 'yes' : 'no',
        ];
    }

    private function flattenLocalizedList(mixed $value): array
    {
        if (! is_array($value) || empty($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return $value;
        }

        foreach (['en', 'id'] as $locale) {
            if (isset($value[$locale]) && is_array($value[$locale]) && ! empty($value[$locale])) {
                return array_values($value[$locale]);
            }
        }

        foreach ($value as $candidate) {
            if (is_array($candidate) && ! empty($candidate)) {
                return array_values($candidate);
            }
        }

        return [];
    }

    protected function applyFilters(Builder $query): void
    {
        if (! empty($this->filters['date'])) {
            $query->where('date', $this->filters['date']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        // Default ordering is set in getQuery; no additional sorting handled here.
    }

    private function translation(RundownItem $item, string $field, string $locale): ?string
    {
        $value = $item->getTranslation($field, $locale, false);

        return $value === '' ? null : $value;
    }
}
