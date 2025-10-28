<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UsersExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return User::query()->with(['roles', 'media', 'links']);
    }

    protected function phoneColumns(): array
    {
        return ['G'];
    }

    public function headings(): array
    {
        return [
            'ID',
            'ULID',
            'Name',
            'Username',
            'Email',
            'Roles',
            'Phone',
            'Birth Date',
            'Gender',
            'Title',
            'Status',
            'Visibility',
            'Email Verified',
            'Created At',
            'Updated At',
            'Website',
            'Instagram',
            'Profile Image',
            'Cover Image',
        ];
    }

    /**
     * @param  User  $user
     */
    public function map($user): array
    {
        // Get profile image URL
        $profileImage = $user->getFirstMediaUrl('profile_image', 'original') ?: '-';

        // Get cover image URL
        $coverImage = $user->getFirstMediaUrl('cover_image', 'original') ?: '-';

        // Get Website and Instagram URLs from links relation
        $website = '-';
        $instagram = '-';

        foreach ($user->links as $link) {
            if (strtolower($link->label) === 'website') {
                $website = $link->url;
            } elseif (strtolower($link->label) === 'instagram') {
                $instagram = $link->url;
            }
        }

        // Format roles with title case
        $roles = $user->roles->pluck('name')->map(fn ($role) => $this->titleCase($role))->join(', ');

        return [
            $user->id,
            $user->ulid ?? '-',
            $user->name,
            $user->username,
            $user->email,
            $roles,
            $user->phone ?? '-',
            $user->birth_date?->format('Y-m-d') ?? '-',
            $this->titleCase($user->gender),
            $user->title ?? '-',
            $this->titleCase($user->status),
            $this->titleCase($user->visibility),
            $user->email_verified_at ? 'Yes' : 'No',
            $user->created_at?->format('Y-m-d H:i:s'),
            $user->updated_at?->format('Y-m-d H:i:s'),
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
            $this->applySearchFilter($query, ['name', 'email', 'username'], $this->filters['search']);
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $this->applyStatusFilter($query, $this->filters['status']);
        }

        // Role filter
        if (isset($this->filters['role'])) {
            $roles = array_map('strtolower', explode(',', $this->filters['role']));
            $query->whereHas('roles', fn ($q) => $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(name)'), $roles));
        }

        // Verified filter
        if (isset($this->filters['verified'])) {
            $query->where(function ($q) {
                $statuses = explode(',', $this->filters['verified']);
                if (in_array('true', $statuses)) {
                    $q->orWhereNotNull('email_verified_at');
                }
                if (in_array('false', $statuses)) {
                    $q->orWhereNull('email_verified_at');
                }
            });
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if ($field === 'roles') {
            $query->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->select('users.*')
                ->groupBy('users.id')
                ->orderByRaw("MIN(roles.name) {$direction}");
        } elseif (in_array($field, ['name', 'email', 'username', 'status', 'email_verified_at', 'created_at', 'updated_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
