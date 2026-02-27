<?php

namespace App\Exports;

use App\Models\EventProduct;
use Illuminate\Database\Eloquent\Builder;

class EventProductsExport extends BaseExport
{
    public function __construct(
        protected int $eventId,
        protected ?array $filters = null,
        protected ?string $sort = null
    ) {}

    protected function getQuery(): Builder
    {
        return EventProduct::query()
            ->where('event_id', $this->eventId);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Category',
            'Name',
            'Description',
            'Price',
            'Unit',
            'Booth Types',
            'Active',
            'Created At',
        ];
    }

    /**
     * @param  EventProduct  $product
     */
    public function map($product): array
    {
        $boothTypes = $product->booth_types
            ? implode(', ', array_map(fn ($t) => $this->titleCase($t), $product->booth_types))
            : '-';

        return [
            $product->id,
            $product->category ?? '-',
            $product->name ?? '-',
            $product->description ?? '-',
            $product->price,
            $product->unit ?? '-',
            $boothTypes,
            $product->is_active ? 'Yes' : 'No',
            $product->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(category) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        if (isset($this->filters['category'])) {
            $query->where('category', $this->filters['category']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort ?? 'order_column');

        if (in_array($field, ['order_column', 'category', 'name', 'price', 'created_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('order_column', 'asc');
        }
    }
}
