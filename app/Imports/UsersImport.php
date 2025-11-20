<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class UsersImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    public function prepareForValidation($data, $index)
    {
        // Normalize phone to string
        if (isset($data['phone']) && ! is_null($data['phone'])) {
            $data['phone'] = (string) $data['phone'];
        }

        // Normalize roles to lowercase (handles comma-separated values)
        if (isset($data['roles']) && ! is_null($data['roles'])) {
            $roles = array_map(fn ($role) => strtolower(trim($role)), explode(',', $data['roles']));
            $data['roles'] = implode(',', $roles);
        }

        // Normalize gender to lowercase
        if (isset($data['gender']) && ! is_null($data['gender'])) {
            $data['gender'] = strtolower(trim($data['gender']));
        }

        // Normalize status to lowercase
        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        // Normalize visibility to lowercase
        if (isset($data['visibility']) && ! is_null($data['visibility'])) {
            $data['visibility'] = strtolower(trim($data['visibility']));
        }

        return $data;
    }

    public function model(array $row): ?User
    {
        // Auto-generate name from email if not provided
        $name = ! empty($row['name']) ? $row['name'] : explode('@', $row['email'])[0];

        // Prepare user data
        $userData = [
            'name' => $name,
            'email' => $row['email'],
            'username' => $row['username'] ?? null,
            'phone' => $row['phone'] ?? null,
            'birth_date' => ! empty($row['birth_date']) ? $row['birth_date'] : null,
            'gender' => $row['gender'] ?? null,
            'title' => $row['title'] ?? null,
            'bio' => $row['bio'] ?? null,
            'status' => $row['status'] ?? 'active',
            'visibility' => $row['visibility'] ?? 'public',
        ];

        // Only add password if provided
        if (! empty($row['password'])) {
            $userData['password'] = Hash::make($row['password']);
        }

        // Create user
        $user = User::create($userData);

        // Assign roles if provided, otherwise assign 'user' role
        if (! empty($row['roles'])) {
            $roles = array_map('trim', explode(',', $row['roles']));
            $user->assignRole($roles);
        } else {
            $user->assignRole('user');
        }

        // Create links from website and instagram columns
        $order = 0;
        if (! empty($row['website'])) {
            $user->links()->create([
                'label' => 'Website',
                'url' => $row['website'],
                'order' => $order++,
                'is_active' => true,
            ]);
        }

        if (! empty($row['instagram'])) {
            $user->links()->create([
                'label' => 'Instagram',
                'url' => $row['instagram'],
                'order' => $order++,
                'is_active' => true,
            ]);
        }

        $this->importedCount++;

        return $user;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username'],
            'roles' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'visibility' => ['nullable', Rule::in(['public', 'private'])],
            'bio' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url', 'max:500'],
            'instagram' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function onFailure(Failure ...$failures): void
    {
        $this->failures = array_merge($this->failures, $failures);
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
