<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
        $query = User::query()->with(['roles', 'media']);

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
            'ULID',
            'Name',
            'Username',
            'Email',
            'Roles',
            'Phone',
            'Birth Date',
            'Gender',
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

        // Get Website and Instagram URLs from links array
        $website = '-';
        $instagram = '-';

        if ($user->links && is_array($user->links)) {
            foreach ($user->links as $link) {
                if (isset($link['label']) && isset($link['url'])) {
                    if (strtolower($link['label']) === 'website') {
                        $website = $link['url'];
                    } elseif (strtolower($link['label']) === 'instagram') {
                        $instagram = $link['url'];
                    }
                }
            }
        }

        return [
            $user->id,
            $user->ulid ?? '-',
            $user->name,
            $user->username,
            $user->email,
            $user->roles->pluck('name')->join(', '),
            $user->phone ?? '-',
            $user->birth_date?->format('Y-m-d') ?? '-',
            $user->gender ?? '-',
            $user->status,
            $user->visibility ?? '-',
            $user->email_verified_at ? 'Yes' : 'No',
            $user->created_at?->format('Y-m-d H:i:s'),
            $user->updated_at?->format('Y-m-d H:i:s'),
            $website,
            $instagram,
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
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$searchTerm}%"]);
            });
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $statuses = array_map('strtolower', explode(',', $this->filters['status']));
            $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), $statuses);
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

    private function applySorting($query): void
    {
        $sortField = $this->sort;
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

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
