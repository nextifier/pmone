<?php

namespace App\Listeners;

use App\Enums\EmailEventType;
use App\Models\EmailMessage;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Records every message SES accepts, keyed by the SES message id that
 * SesV2Transport writes back into the "X-SES-Message-ID" header. Later SNS
 * events carry that same id, which is how a bounce finds its message.
 *
 * Other mailers (resend, cloudflare, log, array) never set that header, so
 * nothing is recorded for them and the dashboard cannot claim a delivery it
 * has no evidence for.
 */
class RecordSentMessage
{
    public function handle(MessageSent $event): void
    {
        $email = $event->message;

        $header = $email->getHeaders()->get('X-SES-Message-ID');

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
                    'configuration_set' => config('mail.mailers.ses-v2.options.ConfigurationSetName'),
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
