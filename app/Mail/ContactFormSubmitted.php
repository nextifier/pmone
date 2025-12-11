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

        return new Envelope(
            subject: $this->submission->subject,
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            replyTo: [
                new \Illuminate\Mail\Mailables\Address(config('mail.from.address')),
            ],
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
