<?php

namespace App\Exports;

use App\Models\ContactFormSubmission;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class ContactFormSubmissionsExport extends BaseExport implements WithColumnWidths
{
    public function columnWidths(): array
    {
        return [
            'A' => 6,   // ID
            'B' => 35,  // Subject
            'C' => 25,  // Name
            'D' => 25,  // Job Title
            'E' => 25,  // Brand Name
            'F' => 30,  // Email
            'G' => 20,  // Phone
            'H' => 20,  // Country
            'I' => 25,  // Project
            'J' => 14,  // Status
            'K' => 45,  // Message
            'L' => 20,  // Created At
            'M' => 20,  // Referral Source
        ];
    }

    protected function getQuery(): Builder
    {
        return ContactFormSubmission::query()->with(['project']);
    }

    protected function phoneColumns(): array
    {
        return ['G'];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Subject',
            'Name',
            'Job Title',
            'Brand Name',
            'Email',
            'Phone',
            'Country',
            'Project',
            'Status',
            'Message',
            'Created At',
            'Referral Source',
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
        $jobTitle = $formData['job_title'] ?? '-';
        $brandName = $formData['brand_name'] ?? '-';
        $email = $formData['email'] ?? '-';
        $phone = $formData['phone'] ?? '-';
        $message = $formData['message'] ?? '-';
        $referralSource = $formData['referral_source'] ?? '-';

        // Clean phone number (remove non-digits except +)
        if ($phone !== '-') {
            $phone = preg_replace('/[^\d+]/', '', $phone);
        }

        $country = $formData['country'] ?? '-';

        return [
            $submission->id,
            $submission->subject ?? '-',
            $name,
            $jobTitle,
            $brandName,
            $email,
            $phone,
            $country,
            $submission->project?->name ?? '-',
            $this->titleCase($submission->status?->value),
            $message,
            $submission->created_at?->format('Y-m-d H:i:s'),
            $referralSource,
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(subject) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(form_data::text) LIKE ?', ["%{$searchTerm}%"]);
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

        // Handle project sorting by joining with projects table
        if (in_array($field, ['project_id', 'project.name'])) {
            $query->leftJoin('projects', 'contact_form_submissions.project_id', '=', 'projects.id')
                ->orderBy('projects.name', $direction)
                ->select('contact_form_submissions.*');
        } elseif (in_array($field, ['subject', 'status', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
