<?php

namespace App\Exports;

use App\Models\PromotionRule;
use Illuminate\Database\Eloquent\Builder;

class PromotionRulesExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return PromotionRule::query()->withCount(['codes', 'appliedAdjustments']);
    }

    public function headings(): array
    {
        return [
            'Name',
            'Slug',
            'Kind',
            'Value Type',
            'Value',
            'Stacking Mode',
            'Priority',
            'Starts At',
            'Ends At',
            'Status',
            'Codes',
            'Applied',
            'Created At',
        ];
    }

    /**
     * @param  PromotionRule  $rule
     */
    public function map($rule): array
    {
        return [
            $rule->name,
            $rule->slug,
            $rule->kind?->value ?? '-',
            $rule->value_type?->value ?? '-',
            (float) $rule->value,
            $rule->stacking_mode?->value ?? '-',
            (int) $rule->priority,
            $rule->starts_at?->format('Y-m-d H:i:s') ?? '-',
            $rule->ends_at?->format('Y-m-d H:i:s') ?? '-',
            $rule->is_active ? 'Active' : 'Inactive',
            (int) ($rule->codes_count ?? 0),
            (int) ($rule->applied_adjustments_count ?? 0),
            $rule->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['name', 'slug'], $this->filters['search']);
        }

        if (isset($this->filters['kind'])) {
            $query->where('kind', $this->filters['kind']);
        }

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', filter_var($this->filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($this->filters['event_id'])) {
            $query->where('event_id', $this->filters['event_id']);
        }

        if (isset($this->filters['trigger_type'])) {
            $query->where('trigger_type', $this->filters['trigger_type']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['name', 'kind', 'priority', 'starts_at', 'ends_at', 'is_active', 'created_at'], true)) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
