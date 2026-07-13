<?php

namespace App\Listeners;

use App\Enums\EmailEventType;
use App\Models\EmailMessage;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Records every message a tracked provider accepts, keyed by the id the
 * transport writes back into a header: "X-Resend-Email-ID" for Resend,
 * "X-SES-Message-ID" for SES. Later webhook events carry that same id, which is
 * how a bounce or open finds its message.
 *
 * Providers that set neither header (cloudflare, log, array) record nothing, so
 * the dashboard never claims a delivery it has no evidence for.
 */
class RecordSentMessage
{
    public function handle(MessageSent $event): void
    {
        $email = $event->message;

        $isResend = $email->getHeaders()->has('X-Resend-Email-ID');
        $header = $email->getHeaders()->get('X-Resend-Email-ID')
            ?? $email->getHeaders()->get('X-SES-Message-ID');

        if ($header === null) {
            return;
        }

        $messageId = trim($header->getBodyAsString());

        if ($messageId === '') {
            return;
        }

        $recipients = array_map(
            fn (Address $address) => $address->getAddress(),
            [...$email->getTo(), ...$email->getCc(), ...$email->getBcc()],
        );

        $from = $email->getFrom();

        try {
            EmailMessage::query()->updateOrCreate(
                ['message_id' => $messageId],
                [
                    'mailer' => config('mail.default'),
                    'from_address' => $from === [] ? '' : $from[0]->getAddress(),
                    'subject' => $email->getSubject(),
                    'recipients' => $recipients,
                    'configuration_set' => $isResend ? null : config('mail.mailers.ses-v2.options.ConfigurationSetName'),
                    'status' => EmailEventType::Send,
                    'status_rank' => EmailEventType::Send->rank(),
                    'sent_at' => now(),
                ],
            );
        } catch (\Throwable $e) {
            // A dashboard row is never worth failing a real send over.
            Log::warning('Could not record a sent email.', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
