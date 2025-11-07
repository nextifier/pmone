<?php

namespace App\Exports;

use App\Models\GaProperty;
use Illuminate\Database\Eloquent\Builder;

class GaPropertiesExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return GaProperty::with('tags');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Property ID',
            'Tags',
            'Status',
            'Sync Frequency (minutes)',
            'Last Synced At',
            'Created At',
        ];
    }

    /**
     * @param  GaProperty  $property
     */
    public function map($property): array
    {
        $tags = $property->tags->pluck('name')->implode(', ');

        return [
            $property->id,
            $property->name,
            $property->property_id,
            $tags,
            $property->is_active ? 'Active' : 'Inactive',
            $property->sync_frequency,
            $property->last_synced_at?->format('Y-m-d H:i:s') ?? 'Never',
            $property->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['name', 'property_id'], $this->filters['search']);
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

        if (in_array($field, ['name', 'property_id', 'is_active', 'sync_frequency', 'last_synced_at', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('last_synced_at', 'desc');
        }
    }
}
