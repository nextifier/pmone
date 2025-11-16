<?php

namespace App\Actions\ContactForm;

use App\Enums\ContactFormStatus;
use App\Jobs\ProcessContactFormSubmission;
use App\Models\ContactFormSubmission;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class SubmitContactFormAction
{
    public function __construct(
        private readonly Project $project,
        private readonly array $formData,
        private readonly ?string $subject = null,
        private readonly ?string $ipAddress = null,
        private readonly ?string $userAgent = null
    ) {}

    public function execute(): ContactFormSubmission
    {
        // Validate project has contact form enabled
        if (! $this->project->isContactFormEnabled()) {
            throw new \Exception('Contact form is not enabled for this project.');
        }

        // Validate email config exists
        $emailConfig = $this->project->getContactFormEmailConfig();
        if (empty($emailConfig['to'])) {
            throw new \Exception('No email recipients configured for this project.');
        }

        // Create submission in database
        $submission = DB::transaction(function () {
            return ContactFormSubmission::create([
                'project_id' => $this->project->id,
                'form_data' => $this->sanitizeFormData($this->formData),
                'subject' => $this->subject ?? "New Contact Form Submission - {$this->project->name}",
                'status' => ContactFormStatus::New->value,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
            ]);
        });

        // Dispatch job to send email asynchronously
        ProcessContactFormSubmission::dispatch($submission);

        return $submission;
    }

    private function sanitizeFormData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Keep message field as-is (already sanitized in FormRequest)
                $sanitized[$key] = $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeFormData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
