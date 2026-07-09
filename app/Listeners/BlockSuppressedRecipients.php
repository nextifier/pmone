<?php

namespace App\Listeners;

use App\Models\EmailSuppression;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Drops recipients that hard-bounced or filed a spam complaint before the
 * message reaches a provider. SES suspends accounts whose bounce rate passes 5%
 * or complaint rate passes 0.1%, and every send to a dead address counts.
 *
 * Must stay synchronous: Mailer cancels the send only when a MessageSending
 * listener returns false.
 */
class BlockSuppressedRecipients
{
    /**
     * Returning false cancels the message. Returning null lets other listeners
     * run, since Mailer dispatches this event with `until()` and any non-null
     * return halts the chain.
     */
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;

        $to = $message->getTo();
        $cc = $message->getCc();
        $bcc = $message->getBcc();

        $everyone = [...$to, ...$cc, ...$bcc];

        if ($everyone === []) {
            return null;
        }

        $suppressed = EmailSuppression::query()
            ->whereIn('email', array_map($this->addressOf(...), $everyone))
            ->pluck('email')
            ->all();

        if ($suppressed === []) {
            return null;
        }

        $keep = fn (array $addresses): array => array_values(array_filter(
            $addresses,
            fn (Address $address) => ! in_array($this->addressOf($address), $suppressed, true),
        ));

        $remainingTo = $keep($to);

        if ($remainingTo === []) {
            Log::info('Cancelled an email addressed only to suppressed recipients.', [
                'recipients' => $suppressed,
                'subject' => $message->getSubject(),
            ]);

            return false;
        }

        $message->to(...$remainingTo);
        $message->cc(...$keep($cc));
        $message->bcc(...$keep($bcc));

        Log::info('Stripped suppressed recipients from an outgoing email.', [
            'recipients' => $suppressed,
            'subject' => $message->getSubject(),
        ]);

        return null;
    }

    private function addressOf(Address $address): string
    {
        return EmailSuppression::normalize($address->getAddress());
    }
}
