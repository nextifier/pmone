<?php

namespace App\Exports;

use App\Models\ShortLink;
use Illuminate\Database\Eloquent\Builder;

class ShortLinksExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return ShortLink::query()->with(['user'])->withCount('clicks');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Slug',
            'Destination URL',
            'Status',
            'Clicks Count',
            'Created By',
            'Created At',
        ];
    }

    /**
     * @param  ShortLink  $shortLink
     */
    public function map($shortLink): array
    {
        return [
            $shortLink->id,
            $shortLink->slug,
            $shortLink->destination_url,
            $shortLink->is_active ? 'Active' : 'Inactive',
            (int) ($shortLink->clicks_count ?? 0),
            $shortLink->user->name ?? '-',
            $shortLink->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['slug', 'destination_url'], $this->filters['search']);
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $query->where(function ($q) {
                $statuses = explode(',', $this->filters['status']);
                foreach ($statuses as $status) {
                    if ($status === 'active') {
                        $q->orWhere('is_active', true);
                    } elseif ($status === 'inactive') {
                        $q->orWhere('is_active', false);
                    }
                }
            });
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['slug', 'destination_url', 'is_active', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
