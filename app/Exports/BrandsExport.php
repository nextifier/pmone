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
            'Country',
            'Province',
            'City',
            'Street Address',
            'Status',
            'Visibility',
            'Business Categories',
            'Events',
            'Members',
            'Created At',
            'Updated At',
            'Profile Image',
            'Brand Logo',
        ];
    }

    /**
     * @param  Brand  $brand
     */
    public function map($brand): array
    {
        $profileImage = $brand->getFirstMediaUrl('profile_image', 'md') ?: '-';
        $brandLogo = $brand->getFirstMediaUrl('brand_logo', 'original') ?: '-';

        $categories = $brand->tags
            ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
            ->pluck('name')
            ->unique()
            ->join(', ') ?: '-';

        $events = $brand->brandEvents->map(fn ($be) => $be->event?->title)->filter()->join(', ') ?: '-';

        $members = $brand->users->pluck('name')->join(', ') ?: '-';

        $address = $brand->address ?? [];

        return [
            $brand->id,
            $brand->ulid,
            $brand->name,
            $brand->slug,
            $brand->company_name ?? '-',
            $brand->company_phone ?? '-',
            $brand->company_email ?? '-',
            $address['country'] ?? '-',
            $address['province'] ?? '-',
            $address['city'] ?? '-',
            $address['street'] ?? '-',
            $this->titleCase($brand->status),
            $this->titleCase($brand->visibility),
            $categories,
            $events,
            $members,
            $brand->created_at?->format('Y-m-d H:i:s'),
            $brand->updated_at?->format('Y-m-d H:i:s'),
            $profileImage,
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

        if (isset($this->filters['country'])) {
            $countries = array_filter(explode(',', $this->filters['country']));
            if (! empty($countries)) {
                $query->where(function ($q) use ($countries) {
                    foreach ($countries as $c) {
                        $q->orWhereRaw("address->>'country' ilike ?", ["%{$c}%"]);
                    }
                });
            }
        }

        if (isset($this->filters['province'])) {
            $provinces = array_filter(explode(',', $this->filters['province']));
            if (! empty($provinces)) {
                $query->where(function ($q) use ($provinces) {
                    foreach ($provinces as $p) {
                        $q->orWhereRaw("address->>'province' ilike ?", ["%{$p}%"]);
                    }
                });
            }
        }

        if (isset($this->filters['city'])) {
            $cities = array_filter(explode(',', $this->filters['city']));
            if (! empty($cities)) {
                $query->where(function ($q) use ($cities) {
                    foreach ($cities as $c) {
                        $q->orWhereRaw("address->>'city' ilike ?", ["%{$c}%"]);
                    }
                });
            }
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
