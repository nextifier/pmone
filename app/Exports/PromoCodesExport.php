<?php

namespace App\Exports;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Builder;

class PromoCodesExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return PromoCode::query()->with('promotionRule:id,name');
    }

    public function headings(): array
    {
        return [
            'Code',
            'Promotion Rule',
            'Usage Limit',
            'Usage Count',
            'Usage Limit Per Email',
            'Valid From',
            'Valid Until',
            'Status',
            'Issued To Email',
            'Created At',
        ];
    }

    /**
     * @param  PromoCode  $code
     */
    public function map($code): array
    {
        return [
            $code->code,
            $code->promotionRule?->name ?? '-',
            $code->usage_limit ?? 'Unlimited',
            (int) $code->usage_count,
            $code->usage_limit_per_email ?? '-',
            $code->valid_from?->format('Y-m-d H:i:s') ?? '-',
            $code->valid_until?->format('Y-m-d H:i:s') ?? '-',
            $code->is_active ? 'Active' : 'Inactive',
            $code->issued_to_email ?? '-',
            $code->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['code', 'issued_to_email'], $this->filters['search']);
        }

        if (isset($this->filters['rule_id'])) {
            $query->where('promotion_rule_id', $this->filters['rule_id']);
        }

        if (isset($this->filters['event_id'])) {
            $query->where('event_id', $this->filters['event_id']);
        }

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', filter_var($this->filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($this->filters['exhausted'])) {
            $query->whereColumn('usage_count', '>=', 'usage_limit')->whereNotNull('usage_limit');
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['code', 'usage_count', 'usage_limit', 'valid_until', 'is_active', 'created_at'], true)) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
