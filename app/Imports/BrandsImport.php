<?php

namespace App\Imports;

use App\Helpers\PhoneCountryHelper;
use App\Imports\Concerns\TracksImportProgress;
use App\Models\Brand;
use Illuminate\Validation\Rules\Email;
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

class BrandsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithEvents, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use Concerns\ImportsFirstSheetOnly, Importable, TracksImportProgress;

    protected array $failures = [];

    protected int $importedCount = 0;

    public function prepareForValidation($data, $index)
    {
        // Normalize email (trim + lowercase)
        if (isset($data['company_email']) && is_string($data['company_email'])) {
            $data['company_email'] = strtolower(trim($data['company_email']));
        }

        // Normalize phone number to international format
        if (isset($data['company_phone']) && ! is_null($data['company_phone'])) {
            $data['company_phone'] = PhoneCountryHelper::normalizePhoneNumber((string) $data['company_phone']);
        }

        // Trim name fields
        foreach (['name', 'company_name'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        if (isset($data['visibility']) && ! is_null($data['visibility'])) {
            $data['visibility'] = strtolower(trim($data['visibility']));
        }

        return $data;
    }

    public function model(array $row): ?Brand
    {
        // Build address array from individual columns
        $address = null;
        $country = $row['country'] ?? null;
        $province = $row['province'] ?? null;
        $city = $row['city'] ?? null;
        $street = $row['street_address'] ?? null;

        if ($country || $province || $city || $street) {
            $address = [
                'country' => $country ? trim($country) : '',
                'province' => $province ? trim($province) : '',
                'city' => $city ? trim($city) : '',
                'street' => $street ? trim($street) : '',
            ];
        }

        $brand = Brand::create([
            'name' => $row['name'],
            'company_name' => $row['company_name'] ?? null,
            'company_email' => $row['company_email'] ?? null,
            'company_phone' => $row['company_phone'] ?? null,
            'address' => $address,
            'status' => $row['status'] ?? 'active',
            'visibility' => $row['visibility'] ?? 'public',
        ]);

        // Sync business categories if provided
        if (! empty($row['business_categories'])) {
            $categories = array_map('trim', explode(',', $row['business_categories']));
            $categories = array_filter($categories);
            if (! empty($categories)) {
                $brand->syncBusinessCategories($categories);
            }
        }

        $this->importedCount++;
        $this->updateProgress($this->importedCount);

        return $brand;
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
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', Email::default(), 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'street_address' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:active,inactive'],
            'visibility' => ['nullable', 'in:public,private'],
            'business_categories' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Brand name is required.',
            'company_email.email' => 'Company email must be a valid email address.',
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
