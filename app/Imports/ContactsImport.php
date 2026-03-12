<?php

namespace App\Imports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class ContactsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    public function prepareForValidation($data, $index)
    {
        if (isset($data['phones']) && ! is_null($data['phones'])) {
            $data['phones'] = (string) $data['phones'];
        }

        if (isset($data['status']) && ! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        if (isset($data['source']) && ! is_null($data['source'])) {
            $data['source'] = strtolower(trim($data['source']));
        }

        return $data;
    }

    public function model(array $row): ?Contact
    {
        // Parse comma-separated emails
        $emails = null;
        if (! empty($row['emails'])) {
            $emails = array_map('trim', explode(',', $row['emails']));
            $emails = array_filter($emails);
            $emails = array_values($emails);
        }

        // Parse comma-separated phones
        $phones = null;
        if (! empty($row['phones'])) {
            $phones = array_map('trim', explode(',', (string) $row['phones']));
            $phones = array_filter($phones);
            $phones = array_values($phones);
        }

        $contact = Contact::create([
            'name' => $row['name'],
            'job_title' => $row['job_title'] ?? null,
            'emails' => $emails,
            'phones' => $phones,
            'company_name' => $row['company_name'] ?? null,
            'website' => $row['website'] ?? null,
            'status' => $row['status'] ?? 'active',
            'source' => $row['source'] ?? 'import',
        ]);

        // Sync contact types if provided
        if (! empty($row['contact_types'])) {
            $types = array_map('trim', explode(',', $row['contact_types']));
            $types = array_filter($types);
            if (! empty($types)) {
                $contact->syncContactTypes($types);
            }
        }

        // Sync business categories if provided
        if (! empty($row['business_categories'])) {
            $categories = array_map('trim', explode(',', $row['business_categories']));
            $categories = array_filter($categories);
            if (! empty($categories)) {
                $contact->syncBusinessCategories($categories);
            }
        }

        $this->importedCount++;

        return $contact;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'emails' => ['nullable', 'string', 'max:1000'],
            'phones' => ['nullable', 'string', 'max:500'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'source' => ['nullable', 'string', 'max:50'],
            'contact_types' => ['nullable', 'string', 'max:500'],
            'business_categories' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Contact name is required.',
            'status.in' => 'Status must be "active", "inactive", or "archived".',
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
