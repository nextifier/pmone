<?php

namespace App\Exports;

use App\Models\GaProperty;
use Illuminate\Database\Eloquent\Builder;

class GaPropertiesExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return GaProperty::query();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Property ID',
            'Account Name',
            'Status',
            'Sync Frequency (minutes)',
            'Rate Limit Per Hour',
            'Last Synced At',
            'Created At',
        ];
    }

    /**
     * @param  GaProperty  $property
     */
    public function map($property): array
    {
        return [
            $property->id,
            $property->name,
            $property->property_id,
            $property->account_name,
            $property->is_active ? 'Active' : 'Inactive',
            $property->sync_frequency,
            $property->rate_limit_per_hour,
            $property->last_synced_at?->format('Y-m-d H:i:s') ?? 'Never',
            $property->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['name', 'property_id', 'account_name'], $this->filters['search']);
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

        if (in_array($field, ['name', 'property_id', 'account_name', 'is_active', 'sync_frequency', 'rate_limit_per_hour', 'last_synced_at', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('last_synced_at', 'desc');
        }
    }
}
