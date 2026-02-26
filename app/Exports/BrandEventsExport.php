<?php

namespace App\Exports;

use App\Models\BrandEvent;
use Illuminate\Database\Eloquent\Builder;

class BrandEventsExport extends BaseExport
{
    public function __construct(
        protected int $eventId,
        protected ?array $filters = null,
        protected ?string $sort = null
    ) {}

    protected function getQuery(): Builder
    {
        return BrandEvent::query()
            ->where('event_id', $this->eventId)
            ->with(['brand.tags', 'sales'])
            ->withCount('promotionPosts');
    }

    protected function phoneColumns(): array
    {
        return ['G'];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Brand Name',
            'Company Name',
            'Company Email',
            'Company Address',
            'Company Phone',
            'Status',
            'Booth Number',
            'Booth Size (sqm)',
            'Booth Type',
            'Categories',
            'Sales',
            'Promo Posts',
            'Created At',
        ];
    }

    /**
     * @param  BrandEvent  $brandEvent
     */
    public function map($brandEvent): array
    {
        $brand = $brandEvent->brand;

        return [
            $brandEvent->id,
            $brand->name ?? '-',
            $brand->company_name ?? '-',
            $brand->company_email ?? '-',
            $brand->company_address ?? '-',
            $brand->company_phone ?? '-',
            $this->titleCase($brandEvent->status),
            $brandEvent->booth_number ?? '-',
            $brandEvent->booth_size ?? '-',
            $brandEvent->booth_type?->label() ?? '-',
            $brand->relationLoaded('tags') ? ($brand->business_categories_list ? implode(', ', $brand->business_categories_list) : '-') : '-',
            $brandEvent->sales?->name ?? '-',
            (int) ($brandEvent->promotion_posts_count ?? 0),
            $brandEvent->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->whereHas('brand', function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort ?? 'order_column');

        if (in_array($field, ['order_column', 'status', 'booth_number', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('order_column', 'asc');
        }
    }
}
