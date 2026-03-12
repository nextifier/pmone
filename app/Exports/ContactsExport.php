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

        return [
            $contact->id,
            $contact->ulid,
            $contact->name,
            $contact->job_title ?? '-',
            $emails,
            $phones,
            $contact->company_name ?? '-',
            $contact->website ?? '-',
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
