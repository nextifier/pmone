<?php

namespace App\Mail;

use App\Models\ContactFormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactFormSubmission $submission
    ) {}

    public function envelope(): Envelope
    {
        $project = $this->submission->project;
        $fromName = data_get($project->settings, 'contact_form.email_config.from_name', $project->name);

        return new Envelope(
            subject: $this->submission->subject,
            from: config('mail.from.address'),
            replyTo: data_get($this->submission->form_data, 'email'),
            metadata: [
                'project_id' => $project->id,
                'submission_id' => $this->submission->id,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-form-submitted',
            with: [
                'project' => $this->submission->project,
                'formData' => $this->submission->form_data,
                'submittedAt' => $this->submission->created_at,
                'ipAddress' => $this->submission->ip_address,
                'subject' => $this->submission->subject,
            ],
        );
    }
}
