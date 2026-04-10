<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportProgress;
use App\Models\Partner;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Validators\Failure;

class PartnersImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithEvents, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use Concerns\ImportsFirstSheetOnly, Importable, TracksImportProgress;

    protected array $failures = [];

    protected int $importedCount = 0;

    public function prepareForValidation($data, $index)
    {
        // Trim name
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (isset($data['website_url']) && is_string($data['website_url'])) {
            $data['website_url'] = trim($data['website_url']);
        }

        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        if (isset($data['visibility']) && ! is_null($data['visibility'])) {
            $data['visibility'] = strtolower(trim($data['visibility']));
        }

        return $data;
    }

    public function model(array $row): ?Partner
    {
        $partner = Partner::create([
            'name' => $row['name'],
            'website_url' => $row['website_url'] ?? null,
            'status' => $row['status'] ?? 'active',
            'visibility' => $row['visibility'] ?? 'public',
        ]);

        $this->importedCount++;
        $this->updateProgress($this->importedCount);

        return $partner;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn (BeforeImport $event) => $this->initProgressTracking($event),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'string', 'url', 'max:500'],
            'status' => ['nullable', 'in:active,inactive'],
            'visibility' => ['nullable', 'in:public,private'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Partner name is required.',
            'website_url.url' => 'Website URL must be a valid URL.',
            'status.in' => 'Status must be either "active" or "inactive".',
            'visibility.in' => 'Visibility must be either "public" or "private".',
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
