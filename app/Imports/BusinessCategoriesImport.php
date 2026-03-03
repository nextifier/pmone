<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Spatie\Tags\Tag;

class BusinessCategoriesImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    protected int $skippedCount = 0;

    public function __construct(protected int $projectId) {}

    public function prepareForValidation($data, $index)
    {
        if (isset($data['name']) && ! is_null($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }

        return $data;
    }

    public function model(array $row): ?Tag
    {
        $type = "business_category:{$this->projectId}";
        $name = trim($row['name']);

        // Skip if category with this name already exists for this project
        $existing = Tag::withType($type)
            ->where('name->en', $name)
            ->first();

        if ($existing) {
            $this->skippedCount++;

            return null;
        }

        $tag = Tag::findOrCreate($name, $type);
        $this->importedCount++;

        return $tag;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.max' => 'Category name must not exceed 255 characters.',
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

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}
