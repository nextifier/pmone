<?php

namespace App\Exports;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Builder;

class BrandsExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return Brand::query()->with(['media', 'users', 'brandEvents', 'tags']);
    }

    protected function phoneColumns(): array
    {
        return ['F'];
    }

    public function headings(): array
    {
        return [
            'ID',
            'ULID',
            'Name',
            'Slug',
            'Company Name',
            'Company Phone',
            'Company Email',
            'Company Address',
            'Status',
            'Visibility',
            'Business Categories',
            'Events',
            'Members',
            'Created At',
            'Updated At',
            'Brand Logo',
        ];
    }

    /**
     * @param  Brand  $brand
     */
    public function map($brand): array
    {
        $brandLogo = $brand->getFirstMediaUrl('brand_logo', 'original') ?: '-';

        $categories = $brand->tagsWithType('business_category')->pluck('name')->join(', ') ?: '-';

        $events = $brand->brandEvents->map(fn ($be) => $be->event?->title)->filter()->join(', ') ?: '-';

        $members = $brand->users->pluck('name')->join(', ') ?: '-';

        return [
            $brand->id,
            $brand->ulid,
            $brand->name,
            $brand->slug,
            $brand->company_name ?? '-',
            $brand->company_phone ?? '-',
            $brand->company_email ?? '-',
            $brand->company_address ?? '-',
            $this->titleCase($brand->status),
            $this->titleCase($brand->visibility),
            $categories,
            $events,
            $members,
            $brand->created_at?->format('Y-m-d H:i:s'),
            $brand->updated_at?->format('Y-m-d H:i:s'),
            $brandLogo,
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['name', 'company_name', 'company_email'], $this->filters['search']);
        }

        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['name', 'company_name', 'status', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }
}
