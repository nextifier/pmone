<?php

namespace App\Imports;

use App\Helpers\PhoneCountryHelper;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class ProjectsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithMultipleSheets, WithValidation
{
    use Concerns\ImportsFirstSheetOnly, Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    public function prepareForValidation($data, $index)
    {
        // Normalize email (trim + lowercase)
        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Normalize phone numbers to international format
        foreach (['phone_sales', 'phone_marketing'] as $field) {
            if (isset($data[$field]) && ! is_null($data[$field])) {
                $data[$field] = PhoneCountryHelper::normalizePhoneNumber((string) $data[$field]);
            }
        }

        // Trim name
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
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

    public function model(array $row): ?Project
    {
        // Prepare phone data in correct format: [{"label":"Sales","number":"+6281212341234"}]
        $phone = [];
        if (! empty($row['phone_sales'])) {
            $phone[] = [
                'label' => 'WhatsApp Sales',
                'number' => $row['phone_sales'],
            ];
        }
        if (! empty($row['phone_marketing'])) {
            $phone[] = [
                'label' => 'WhatsApp Marketing',
                'number' => $row['phone_marketing'],
            ];
        }

        // Create project
        $project = Project::create([
            'name' => $row['name'],
            'username' => $row['username'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => ! empty($phone) ? $phone : null,
            'bio' => $row['bio'] ?? null,
            'status' => $row['status'] ?? 'active',
            'visibility' => $row['visibility'] ?? 'public',
            'created_by' => Auth::id(),
        ]);

        // Create links from website and instagram columns
        $order = 0;
        if (! empty($row['website'])) {
            $project->links()->create([
                'label' => 'Website',
                'url' => $row['website'],
                'order' => $order++,
                'is_active' => true,
            ]);
        }

        if (! empty($row['instagram'])) {
            $project->links()->create([
                'label' => 'Instagram',
                'url' => $row['instagram'],
                'order' => $order++,
                'is_active' => true,
            ]);
        }

        $this->importedCount++;

        return $project;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._\-\']+$/', 'unique:projects,username'],
            'email' => ['nullable', Email::default(), 'max:255'],
            'phone_sales' => ['nullable', 'string', 'max:20'],
            'phone_marketing' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'visibility' => ['nullable', Rule::in(['public', 'private', 'members_only'])],
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
