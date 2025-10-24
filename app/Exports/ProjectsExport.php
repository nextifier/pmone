<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        protected ?array $filters = null,
        protected ?string $sort = null
    ) {}

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Project::query()->with(['members', 'links', 'media']);

        // Apply filters if provided
        if ($this->filters) {
            $this->applyFilters($query);
        }

        // Apply sorting if provided
        if ($this->sort) {
            $this->applySorting($query);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Username',
            'Bio',
            'Email',
            'Phone',
            'Status',
            'Visibility',
            'Members',
            'Links',
            'Created At',
            'Updated At',
            'Profile Image',
            'Cover Image',
        ];
    }

    /**
     * @param  Project  $project
     */
    public function map($project): array
    {
        // Get profile image URL
        $profileImage = $project->getFirstMediaUrl('profile_image', 'original') ?: '-';

        // Get cover image URL
        $coverImage = $project->getFirstMediaUrl('cover_image', 'original') ?: '-';

        // Format members
        $members = $project->members->pluck('name')->join(', ') ?: '-';

        // Format links
        $links = '-';
        if ($project->links && $project->links->isNotEmpty()) {
            $linksArray = $project->links->map(function ($link) {
                return $link->label.': '.$link->url;
            })->toArray();
            $links = implode('; ', $linksArray);
        }

        // Format phone
        $phone = '-';
        if ($project->phone && is_array($project->phone) && count($project->phone) > 0) {
            $phone = implode(', ', array_filter($project->phone));
        }

        return [
            $project->id,
            $project->name,
            $project->username,
            $project->bio ?? '-',
            $project->email ?? '-',
            $phone,
            $project->status,
            $project->visibility ?? '-',
            $members,
            $links,
            $project->created_at?->format('Y-m-d H:i:s'),
            $project->updated_at?->format('Y-m-d H:i:s'),
            $profileImage,
            $coverImage,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function applyFilters($query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $searchTerm = strtolower($this->filters['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $statuses = array_map('strtolower', explode(',', $this->filters['status']));
            $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), $statuses);
        }

        // Visibility filter
        if (isset($this->filters['visibility'])) {
            $visibilities = array_map('strtolower', explode(',', $this->filters['visibility']));
            $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(visibility)'), $visibilities);
        }
    }

    private function applySorting($query): void
    {
        $sortField = $this->sort;
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        if (in_array($field, ['name', 'username', 'email', 'status', 'visibility', 'order_column', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('order_column', 'asc');
        }
    }
}
