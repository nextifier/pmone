<?php

namespace App\Listeners;

use App\Enums\EmailEventType;
use App\Models\EmailMessage;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Records every message Resend accepts, keyed by the id its transport writes
 * back into the "X-Resend-Email-ID" header. Later webhook events carry that same
 * id, which is how a bounce or open finds its message.
 *
 * Providers that set no such header (cloudflare, log, array) record nothing, so
 * the dashboard never claims a delivery it has no evidence for.
 */
class RecordSentMessage
{
    public function handle(MessageSent $event): void
    {
        $email = $event->message;

        $header = $email->getHeaders()->get('X-Resend-Email-ID');

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
                    'mailer' => 'resend',
                    'from_address' => $from === [] ? '' : $from[0]->getAddress(),
                    'subject' => $email->getSubject(),
                    'recipients' => $recipients,
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
