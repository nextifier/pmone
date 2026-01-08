<?php

namespace App\Exports;

use App\Models\ContactFormSubmission;
use Illuminate\Database\Eloquent\Builder;

class ContactFormSubmissionsExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return ContactFormSubmission::query()->with(['project', 'followedUpByUser']);
    }

    protected function phoneColumns(): array
    {
        return ['E'];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Subject',
            'Name',
            'Email',
            'Phone',
            'Project',
            'Status',
            'Message',
            'Created At',
            'Followed Up At',
            'Followed Up By',
        ];
    }

    /**
     * @param  ContactFormSubmission  $submission
     */
    public function map($submission): array
    {
        // Extract form data
        $formData = $submission->form_data ?? [];
        $name = $formData['name'] ?? '-';
        $email = $formData['email'] ?? '-';
        $phone = $formData['phone'] ?? '-';
        $message = $formData['message'] ?? '-';

        // Clean phone number (remove non-digits except +)
        if ($phone !== '-') {
            $phone = preg_replace('/[^\d+]/', '', $phone);
        }

        return [
            $submission->id,
            $submission->subject ?? '-',
            $name,
            $email,
            $phone,
            $submission->project?->name ?? '-',
            $this->titleCase($submission->status?->value),
            $message,
            $submission->created_at?->format('Y-m-d H:i:s'),
            $submission->followed_up_at?->format('Y-m-d H:i:s') ?? '-',
            $submission->followedUpByUser?->name ?? '-',
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(subject) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(form_data) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }

        // Project filter
        if (isset($this->filters['project'])) {
            $projectIds = explode(',', $this->filters['project']);
            $query->whereIn('project_id', $projectIds);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort ?? '-created_at');

        if (in_array($field, ['subject', 'status', 'created_at', 'updated_at', 'followed_up_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
