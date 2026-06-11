<?php

namespace App\Mail;

use App\Models\FormResponse;
use App\Support\FormFieldTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormResponseSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FormResponse $response
    ) {}

    public function envelope(): Envelope
    {
        $form = $this->response->form;
        $project = $form?->project;

        $emailSubject = 'New Response - '.($form?->title ?? 'Form');
        if ($project && $project->name) {
            $emailSubject .= ' - '.$project->name;
        }

        return new Envelope(
            subject: $emailSubject,
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            metadata: [
                'form_id' => $form?->id,
                'response_id' => $this->response->id,
            ],
        );
    }

    public function content(): Content
    {
        $form = $this->response->form;
        $responseData = $this->response->response_data ?? [];

        $answers = $form->fields
            ->reject(fn ($field) => $field->type === 'section')
            ->map(fn ($field) => [
                'label' => $field->label,
                'value' => FormFieldTypes::formatValue($field, $responseData[$field->ulid] ?? null),
            ])
            ->values()
            ->all();

        return new Content(
            markdown: 'emails.form-response-submitted',
            with: [
                'form' => $form,
                'answers' => $answers,
                'respondentEmail' => $this->response->respondent_email,
                'submittedAt' => $this->response->submitted_at,
                'ipAddress' => $this->response->ip_address,
            ],
        );
    }
}
