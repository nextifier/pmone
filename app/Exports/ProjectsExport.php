<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class ProjectsExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return Project::query()->with(['members', 'links', 'media']);
    }

    protected function phoneColumns(): array
    {
        return ['F', 'G'];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Username',
            'Bio',
            'Email',
            'Phone Sales',
            'Phone Marketing',
            'Status',
            'Visibility',
            'Members',
            'Created At',
            'Updated At',
            'Website',
            'Instagram',
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

        // Format phone - separate Sales and Marketing
        $phoneSales = '-';
        $phoneMarketing = '-';
        if ($project->phone && is_array($project->phone) && count($project->phone) > 0) {
            foreach ($project->phone as $phoneData) {
                if (is_array($phoneData) && isset($phoneData['number'])) {
                    $label = strtolower($phoneData['label'] ?? '');
                    if ($label === 'sales') {
                        $phoneSales = $phoneData['number'];
                    } elseif ($label === 'marketing') {
                        $phoneMarketing = $phoneData['number'];
                    }
                }
            }
        }

        // Get Website and Instagram URLs from links relation
        $website = '-';
        $instagram = '-';

        foreach ($project->links as $link) {
            if (strtolower($link->label) === 'website') {
                $website = $link->url;
            } elseif (strtolower($link->label) === 'instagram') {
                $instagram = $link->url;
            }
        }

        return [
            $project->id,
            $project->name,
            $project->username,
            $project->bio ?? '-',
            $project->email ?? '-',
            $phoneSales,
            $phoneMarketing,
            $this->titleCase($project->status),
            $this->titleCase($project->visibility),
            $members,
            $project->created_at?->format('Y-m-d H:i:s'),
            $project->updated_at?->format('Y-m-d H:i:s'),
            $website,
            $instagram,
            $profileImage,
            $coverImage,
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        // Search filter
        if (isset($this->filters['search'])) {
            $this->applySearchFilter($query, ['name', 'username', 'email'], $this->filters['search']);
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }

        // Visibility filter
        if (isset($this->filters['visibility'])) {
            $visibilities = array_map('strtolower', explode(',', $this->filters['visibility']));
            $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(visibility)'), $visibilities);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['name', 'username', 'email', 'status', 'visibility', 'order_column', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('order_column', 'asc');
        }
    }
}
