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

    public function model(array $row): ?User
    {
        // Prepare links array as array of objects with label and url
        $links = [];

        if (! empty($row['website'])) {
            $links[] = [
                'label' => 'Website',
                'url' => $row['website'],
            ];
        }

        if (! empty($row['instagram'])) {
            $links[] = [
                'label' => 'Instagram',
                'url' => $row['instagram'],
            ];
        }

        // Create user
        $user = User::create([
            'name' => $row['name'],
            'email' => $row['email'],
            'username' => $row['username'] ?? null,
            'phone' => $row['phone'] ?? null,
            'birth_date' => ! empty($row['birth_date']) ? $row['birth_date'] : null,
            'gender' => $row['gender'] ?? null,
            'bio' => $row['bio'] ?? null,
            'links' => ! empty($links) ? $links : null,
            'status' => $row['status'] ?? 'active',
            'visibility' => $row['visibility'] ?? 'public',
            'password' => Hash::make('password'),
        ]);

        // Assign roles if provided, otherwise assign 'user' role
        if (! empty($row['roles'])) {
            $roles = array_map('trim', explode(',', $row['roles']));
            $user->assignRole($roles);
        } else {
            $user->assignRole('user');
        }

        $this->importedCount++;

        return $user;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._]+$/', 'unique:users,username'],
            'roles' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
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
