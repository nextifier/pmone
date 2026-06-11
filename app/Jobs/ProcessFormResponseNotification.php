<?php

namespace App\Jobs;

use App\Mail\FormResponseSubmitted;
use App\Models\FormResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessFormResponseNotification implements ShouldQueue
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

    public function __construct(
        public FormResponse $response,
    ) {}

    public function handle(): void
    {
        try {
            $form = $this->response->form;

            if (! $form) {
                Log::warning('Form not found for response notification', [
                    'response_id' => $this->response->id,
                ]);

                return;
            }

            $config = $form->settings['notification_emails'] ?? [];

            // Backward-compat: a flat list of emails means "to" recipients only.
            if (array_is_list($config)) {
                $config = ['to' => $config];
            }

            $clean = fn (mixed $emails): array => collect(is_array($emails) ? $emails : [])
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->values()
                ->all();

            $to = $clean($config['to'] ?? []);
            $cc = $clean($config['cc'] ?? []);
            $bcc = $clean($config['bcc'] ?? []);

            if (empty($to)) {
                Log::warning('No notification recipients configured for form', [
                    'form_id' => $form->id,
                    'response_id' => $this->response->id,
                ]);

                return;
            }

            $mailable = new FormResponseSubmitted($this->response);
            $mailable->to($to);

            if (! empty($cc)) {
                $mailable->cc($cc);
            }

            if (! empty($bcc)) {
                $mailable->bcc($bcc);
            }

            $respondentEmail = $this->response->respondent_email;
            if ($respondentEmail && filter_var($respondentEmail, FILTER_VALIDATE_EMAIL)) {
                $mailable->replyTo($respondentEmail);
            }

            Mail::send($mailable);

            Log::info('Form response notification sent successfully', [
                'form_id' => $form->id,
                'response_id' => $this->response->id,
                'recipients' => ['to' => $to, 'cc' => $cc, 'bcc' => $bcc],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send form response notification', [
                'response_id' => $this->response->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
