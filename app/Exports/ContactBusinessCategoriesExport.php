<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Tags\Tag;

class ContactBusinessCategoriesExport extends BaseExport
{
    public function __construct(
        protected ?array $filters = null,
        protected ?string $sort = null
    ) {}

    protected function getQuery(): Builder
    {
        return Tag::withType('business_category');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Order',
            'Created At',
        ];
    }

    /**
     * @param  Tag  $tag
     */
    public function map($tag): array
    {
        return [
            $tag->id,
            $tag->name,
            $tag->order_column,
            $tag->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->whereRaw('LOWER(name::text) LIKE ?', ["%{$searchTerm}%"]);
        }
    }

    protected function applySorting(Builder $query): void
    {
        if ($this->sort) {
            [$field, $direction] = $this->parseSortField($this->sort);

            if (in_array($field, ['name', 'order_column', 'created_at'])) {
                $query->orderBy($field, $direction);
            } else {
                $query->ordered();
            }
        } else {
            $query->ordered();
        }
    }
}
