<?php

namespace App\Exports;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Builder;

class PartnersExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return Partner::query()->with(['media', 'partnerCategories.event']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'ULID',
            'Name',
            'Slug',
            'Website URL',
            'Status',
            'Visibility',
            'Events',
            'Created At',
            'Updated At',
            'Partner Logo',
        ];
    }

    /**
     * @param  Partner  $partner
     */
    public function map($partner): array
    {
        $partnerLogo = $partner->getFirstMediaUrl('partner_logo', 'original') ?: '-';

        $events = $partner->partnerCategories
            ->map(fn ($cat) => $cat->event?->title)
            ->filter()
            ->unique()
            ->join(', ') ?: '-';

        return [
            $partner->id,
            $partner->ulid,
            $partner->name,
            $partner->slug,
            $partner->website_url ?? '-',
            $this->titleCase($partner->status),
            $this->titleCase($partner->visibility),
            $events,
            $partner->created_at?->format('Y-m-d H:i:s'),
            $partner->updated_at?->format('Y-m-d H:i:s'),
            $partnerLogo,
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['name', 'website_url'], $this->filters['search']);
        }

        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['name', 'status', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }
}
