<?php

namespace App\Jobs;

use App\Mail\ContactFormSubmitted;
use App\Models\ContactFormSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessContactFormSubmission implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContactFormSubmission $submission,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $project = $this->submission->project;

            if (! $project) {
                Log::warning('Project not found for contact form submission', [
                    'submission_id' => $this->submission->id,
                ]);

                return;
            }

            $emailConfig = $project->getContactFormEmailConfig();

            if (empty($emailConfig['to'])) {
                Log::warning('No email recipients configured for project', [
                    'project_id' => $project->id,
                    'submission_id' => $this->submission->id,
                ]);

                return;
            }

            // Send email to configured recipients
            $mailable = new ContactFormSubmitted($this->submission);

            // Set to recipients
            $mailable->to($emailConfig['to']);

            // Set cc recipients if configured
            if (! empty($emailConfig['cc'])) {
                $mailable->cc($emailConfig['cc']);
            }

            // Set bcc recipients if configured
            if (! empty($emailConfig['bcc'])) {
                $mailable->bcc($emailConfig['bcc']);
            }

            // Set reply-to if configured and form has email
            $formEmail = data_get($this->submission->form_data, 'email');
            if ($formEmail && filter_var($formEmail, FILTER_VALIDATE_EMAIL)) {
                $mailable->replyTo($formEmail);
            } elseif (! empty($emailConfig['reply_to'])) {
                $mailable->replyTo($emailConfig['reply_to']);
            }

            Mail::send($mailable);

            Log::info('Contact form submission email sent successfully', [
                'submission_id' => $this->submission->id,
                'project_id' => $project->id,
                'recipients' => $emailConfig['to'],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send contact form submission email', [
                'submission_id' => $this->submission->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to allow retry
        }
    }
}
