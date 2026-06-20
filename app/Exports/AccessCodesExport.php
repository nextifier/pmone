<?php

namespace App\Exports;

use App\Models\AccessCode;
use Illuminate\Database\Eloquent\Builder;

class AccessCodesExport extends BaseExport
{
    public function __construct(
        protected int $eventId,
        ?array $filters = null,
    ) {
        parent::__construct($filters, null);
    }

    protected function getQuery(): Builder
    {
        return AccessCode::query()
            ->where('event_id', $this->eventId)
            ->with('batch:id,name')
            ->withCount('redemptions')
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Code',
            'Kind',
            'Status',
            'Batch',
            'Max Uses',
            'Used Count',
            'Redemptions',
            'Bind Email',
            'Bind Phone',
            'Price Effect',
            'Price Value',
            'Valid From',
            'Valid Until',
            'Created At',
        ];
    }

    /**
     * @param  AccessCode  $code
     */
    public function map($code): array
    {
        return [
            $code->code,
            $this->titleCase($code->kind?->value),
            $this->titleCase($code->status?->value),
            $code->batch?->name ?? '-',
            $code->max_uses ?? 'Unlimited',
            (int) $code->used_count,
            (int) ($code->redemptions_count ?? 0),
            $code->bind_email ?? '-',
            $code->bind_phone ?? '-',
            $this->titleCase($code->price_effect?->value),
            $code->price_value !== null ? (float) $code->price_value : '-',
            $code->valid_from?->format('Y-m-d H:i:s') ?? '-',
            $code->valid_until?->format('Y-m-d H:i:s') ?? '-',
            $code->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (! empty($this->filters['search'])) {
            $this->applySearchFilter($query, ['code', 'bind_email', 'bind_phone'], $this->filters['search']);
        }

        if (! empty($this->filters['kind'])) {
            $query->where('kind', $this->filters['kind']);
        }

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }

    protected function phoneColumns(): array
    {
        return ['I'];
    }
}
