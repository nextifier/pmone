<?php

namespace App\Imports;

use App\Enums\ContactFormStatus;
use App\Models\ContactFormSubmission;
use App\Models\Project;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class ContactFormSubmissionsImport implements SkipsEmptyRows, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use Importable;

    protected array $failures = [];

    protected int $importedCount = 0;

    /** @var array<string, int> */
    protected array $projectCache = [];

    public function prepareForValidation($data, $index)
    {
        // Normalize optional fields - treat "-" as null
        $optionalFields = ['brand_name', 'phone', 'subject', 'status', 'created_at'];
        foreach ($optionalFields as $field) {
            $data[$field] = $this->normalizeValue($data[$field] ?? null);
        }

        // Normalize status to lowercase if not null
        if (! is_null($data['status'])) {
            $data['status'] = strtolower(trim($data['status']));
        }

        // Ensure phone is string (Excel might read as number)
        if (! is_null($data['phone'])) {
            $data['phone'] = (string) $data['phone'];
        }

        return $data;
    }

    /**
     * Normalize a value - treat "-" or empty strings as null.
     */
    protected function normalizeValue(mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            // Treat "-" or empty string as null
            if ($trimmed === '-' || $trimmed === '') {
                return null;
            }

            return $trimmed;
        }

        return $value;
    }

    public function model(array $row): ?ContactFormSubmission
    {
        // Get project ID from project name
        $projectName = trim($row['project'] ?? '');
        $projectId = $this->getProjectIdByName($projectName);

        if (! $projectId) {
            // Skip row if project not found
            return null;
        }

        // Determine status
        $status = ContactFormStatus::New;
        if (isset($row['status']) && ! empty($row['status'])) {
            $statusMap = [
                'new' => ContactFormStatus::New,
                'in_progress' => ContactFormStatus::InProgress,
                'in progress' => ContactFormStatus::InProgress,
                'completed' => ContactFormStatus::Completed,
                'archived' => ContactFormStatus::Archived,
            ];
            $status = $statusMap[strtolower(trim($row['status']))] ?? ContactFormStatus::New;
        }

        // Build form_data from individual fields
        $formData = [];

        if (! empty($row['name'])) {
            $formData['name'] = trim($row['name']);
        }

        if (! empty($row['email'])) {
            $formData['email'] = trim($row['email']);
        }

        // Only add optional fields if they have actual values (not "-" or empty)
        $phone = $this->normalizeValue($row['phone'] ?? null);
        if (! is_null($phone)) {
            $formData['phone'] = (string) $phone;
        }

        $brandName = $this->normalizeValue($row['brand_name'] ?? null);
        if (! is_null($brandName)) {
            $formData['brand_name'] = $brandName;
        }

        // Parse created_at date (already normalized in prepareForValidation)
        $createdAt = null;
        $createdAtValue = $row['created_at'] ?? null;
        if (! is_null($createdAtValue)) {
            try {
                // Handle Excel serial date or string date
                if (is_numeric($createdAtValue)) {
                    // Excel serial date (days since 1900-01-01)
                    $createdAt = Carbon::createFromTimestamp(
                        ($createdAtValue - 25569) * 86400
                    );
                } else {
                    // Try to parse as string date
                    $createdAt = Carbon::parse($createdAtValue);
                }
            } catch (\Exception $e) {
                $createdAt = null;
            }
        }

        // Normalize subject (already done in prepareForValidation but ensure consistency)
        $subject = $this->normalizeValue($row['subject'] ?? null);

        // Create submission
        $submission = new ContactFormSubmission([
            'project_id' => $projectId,
            'subject' => $subject,
            'form_data' => $formData,
            'status' => $status,
        ]);

        // Set timestamps manually if created_at is provided
        if ($createdAt) {
            $submission->created_at = $createdAt;
            $submission->updated_at = $createdAt;
        }

        $submission->save();
        $this->importedCount++;

        return $submission;
    }

    protected function getProjectIdByName(string $projectName): ?int
    {
        if (empty($projectName)) {
            return null;
        }

        // Check cache first
        if (isset($this->projectCache[$projectName])) {
            return $this->projectCache[$projectName];
        }

        // Find project by name
        $project = Project::where('name', $projectName)->first();

        if ($project) {
            $this->projectCache[$projectName] = $project->id;

            return $project->id;
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'project' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'created_at' => ['nullable'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.max' => 'The name must not exceed 255 characters.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'phone.max' => 'The phone number must not exceed 50 characters.',
            'project.required' => 'The project field is required.',
            'subject.max' => 'The subject must not exceed 255 characters.',
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
