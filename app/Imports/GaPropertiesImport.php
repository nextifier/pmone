<?php

namespace App\Imports;

use App\Models\GaProperty;
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
        // Convert property_id to string (Excel may read it as number)
        if (isset($data['property_id']) && ! is_null($data['property_id'])) {
            $data['property_id'] = (string) $data['property_id'];
        }

        // Normalize status to lowercase
        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        // Set default values if not provided
        if (! isset($data['sync_frequency']) || empty($data['sync_frequency'])) {
            $data['sync_frequency'] = 10;
        }

        // Parse tags if provided
        if (isset($data['tags_comma_separated']) && ! empty($data['tags_comma_separated'])) {
            $data['tags'] = array_filter(
                array_map('trim', explode(',', $data['tags_comma_separated']))
            );
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
            'is_active' => $isActive,
            'sync_frequency' => (int) ($row['sync_frequency'] ?? 10),
        ]);

        // Sync tags if provided
        if (isset($row['tags']) && is_array($row['tags']) && ! empty($row['tags'])) {
            $property->syncTags($row['tags']);
        }

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
            'tags_comma_separated' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'sync_frequency' => ['nullable', 'integer', 'min:5', 'max:60'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'The property name field is required.',
            'property_id.required' => 'The property ID is required.',
            'property_id.unique' => 'This property ID is already in use.',
            'sync_frequency.integer' => 'Sync frequency must be a number.',
            'sync_frequency.min' => 'Sync frequency must be at least 5 minutes.',
            'sync_frequency.max' => 'Sync frequency must not exceed 60 minutes.',
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
