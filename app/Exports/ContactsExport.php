<?php

namespace App\Exports;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;

class ContactsExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return Contact::query()->with(['tags', 'projects']);
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
            'Job Title',
            'Emails',
            'Phones',
            'Company Name',
            'Website',
            'Country',
            'Province',
            'City',
            'Street Address',
            'Status',
            'Source',
            'Contact Types',
            'Business Categories',
            'Tags',
            'Projects',
            'Notes',
            'Created At',
            'Updated At',
        ];
    }

    /**
     * @param  Contact  $contact
     */
    public function map($contact): array
    {
        $emails = is_array($contact->emails) ? implode(', ', $contact->emails) : '-';
        $phones = is_array($contact->phones) ? implode(', ', $contact->phones) : '-';

        $types = $contact->tags
            ->filter(fn ($tag) => $tag->type === 'contact_type')
            ->pluck('name')
            ->join(', ') ?: '-';

        $categories = $contact->tags
            ->filter(fn ($tag) => str_starts_with($tag->type, 'business_category'))
            ->pluck('name')
            ->unique()
            ->join(', ') ?: '-';

        $tags = $contact->tags
            ->filter(fn ($tag) => $tag->type === 'contact_tag')
            ->pluck('name')
            ->join(', ') ?: '-';

        $projects = $contact->projects->pluck('name')->join(', ') ?: '-';

        $address = $contact->address;

        return [
            $contact->id,
            $contact->ulid,
            $contact->name,
            $contact->job_title ?? '-',
            $emails,
            $phones,
            $contact->company_name ?? '-',
            $contact->website ?? '-',
            $address['country'] ?? '-',
            $address['province'] ?? '-',
            $address['city'] ?? '-',
            $address['street'] ?? '-',
            $this->titleCase($contact->status->value),
            $this->titleCase($contact->source),
            $types,
            $categories,
            $tags,
            $projects,
            $contact->notes ?? '-',
            $contact->created_at?->format('Y-m-d H:i:s'),
            $contact->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['name', 'company_name', 'job_title'], $this->filters['search']);
        }

        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }

        if (isset($this->filters['source'])) {
            $sources = array_map('strtolower', explode(',', $this->filters['source']));
            $query->whereIn('source', $sources);
        }

        if (isset($this->filters['type'])) {
            $types = array_filter(explode(',', $this->filters['type']));
            if (! empty($types)) {
                $query->withAnyTags($types, 'contact_type');
            }
        }

        if (isset($this->filters['business_category'])) {
            $categories = array_filter(explode(',', $this->filters['business_category']));
            if (! empty($categories)) {
                $query->withAnyTags($categories, 'business_category');
            }
        }

        if (isset($this->filters['tag'])) {
            $tags = array_filter(explode(',', $this->filters['tag']));
            if (! empty($tags)) {
                $query->withAnyTags($tags, 'contact_tag');
            }
        }

        if (isset($this->filters['job_title'])) {
            $query->where('job_title', 'ilike', "%{$this->filters['job_title']}%");
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

        if (isset($this->filters['project'])) {
            $projectIds = array_filter(array_map('intval', explode(',', $this->filters['project'])));
            if (count($projectIds) === 1) {
                $query->whereHas('projects', fn ($q) => $q->where('projects.id', $projectIds[0]));
            } elseif (count($projectIds) > 1) {
                $query->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $projectIds));
            }
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['name', 'company_name', 'status', 'source', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
    }
}
