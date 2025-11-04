<?php

namespace App\Imports;

use App\Models\ShortLink;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class ShortLinksImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    protected int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function prepareForValidation($data, $index)
    {
        // Normalize status to lowercase
        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        return $data;
    }

    public function model(array $row): ?ShortLink
    {
        // Determine is_active status
        $isActive = true;
        if (isset($row['status'])) {
            $isActive = in_array(strtolower($row['status']), ['active', '1', 'yes', 'true']);
        }

        // Create short link
        $shortLink = ShortLink::create([
            'user_id' => $this->userId,
            'slug' => $row['slug'],
            'destination_url' => $row['destination_url'],
            'is_active' => $isActive,
        ]);

        $this->importedCount++;

        return $shortLink;
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._\-]+$/',
                'unique:short_links,slug',
                'unique:users,username',
            ],
            'destination_url' => ['required', 'url', 'max:2000'],
            'status' => ['nullable', 'string'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'slug.required' => 'The slug field is required.',
            'slug.unique' => 'This slug is already taken.',
            'slug.regex' => 'Slug can only contain letters, numbers, dots, underscores, and hyphens.',
            'destination_url.required' => 'The destination URL is required.',
            'destination_url.url' => 'Please enter a valid URL.',
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
