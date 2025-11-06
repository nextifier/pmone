<?php

namespace App\Imports;

use App\Models\GaProperty;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class GaPropertiesImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    public function prepareForValidation($data, $index)
    {
        // Normalize status to lowercase
        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        // Set default values if not provided
        if (! isset($data['sync_frequency']) || empty($data['sync_frequency'])) {
            $data['sync_frequency'] = 10;
        }

        if (! isset($data['rate_limit_per_hour']) || empty($data['rate_limit_per_hour'])) {
            $data['rate_limit_per_hour'] = 12;
        }

        return $data;
    }

    public function model(array $row): ?GaProperty
    {
        // Determine is_active status
        $isActive = true;
        if (isset($row['status'])) {
            $isActive = in_array(strtolower($row['status']), ['active', '1', 'yes', 'true']);
        }

        // Create GA property
        $property = GaProperty::create([
            'name' => $row['name'],
            'property_id' => $row['property_id'],
            'account_name' => $row['account_name'] ?? null,
            'is_active' => $isActive,
            'sync_frequency' => (int) ($row['sync_frequency'] ?? 10),
            'rate_limit_per_hour' => (int) ($row['rate_limit_per_hour'] ?? 12),
        ]);

        $this->importedCount++;

        return $property;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'property_id' => [
                'required',
                'string',
                'max:255',
                'unique:ga_properties,property_id',
            ],
            'account_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'sync_frequency' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rate_limit_per_hour' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'The property name field is required.',
            'property_id.required' => 'The property ID is required.',
            'property_id.unique' => 'This property ID is already in use.',
            'sync_frequency.integer' => 'Sync frequency must be a number.',
            'sync_frequency.min' => 'Sync frequency must be at least 1 minute.',
            'sync_frequency.max' => 'Sync frequency must not exceed 1440 minutes (24 hours).',
            'rate_limit_per_hour.integer' => 'Rate limit must be a number.',
            'rate_limit_per_hour.min' => 'Rate limit must be at least 1.',
            'rate_limit_per_hour.max' => 'Rate limit must not exceed 100.',
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
