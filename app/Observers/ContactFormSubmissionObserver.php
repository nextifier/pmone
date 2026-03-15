<?php

namespace App\Observers;

use App\Models\Contact;
use App\Models\ContactFormSubmission;

class ContactFormSubmissionObserver
{
    /**
     * Handle the ContactFormSubmission "created" event.
     */
    public function created(ContactFormSubmission $submission): void
    {
        $formData = $submission->form_data;

        if (empty($formData['name'])) {
            return;
        }

        $contact = Contact::create([
            'name' => $formData['name'],
            'emails' => ! empty($formData['email']) ? [$formData['email']] : null,
            'phones' => ! empty($formData['phone']) ? [$formData['phone']] : null,
            'company_name' => $formData['brand_name'] ?? null,
            'job_title' => $formData['job_title'] ?? null,
            'address' => ! empty($formData['country']) ? ['country' => $formData['country']] : null,
            'source' => 'website',
        ]);

        // Sync contact type based on subject
        $subject = $submission->subject ?? '';

        if (stripos($subject, 'Exhibitor') !== false) {
            $contact->syncContactTypes(['exhibitor']);
        } elseif (stripos($subject, 'Media Partner') !== false) {
            $contact->syncContactTypes(['media-partner']);
        }

        // Attach contact to the submission's project
        $contact->projects()->syncWithoutDetaching([$submission->project_id]);
    }
}
