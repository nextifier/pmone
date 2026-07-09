<?php

namespace App\Services\Ses;

use App\Enums\EmailEventType;
use App\Enums\EmailSuppressionReason;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use Illuminate\Support\Carbon;

/**
 * Turns one SES event notification into rows: an event per affected recipient,
 * an updated message status, and a suppression entry when the address is dead
 * or its owner complained.
 */
class SesEventRecorder
{
    /**
     * Returns the recognised event type, or null when SES sent something this
     * application does not track.
     *
     * @param  array<string, mixed>  $event
     */
    public function record(array $event): ?EmailEventType
    {
        // Configuration-set destinations send "eventType"; the older
        // identity-level notifications send "notificationType".
        $type = EmailEventType::fromSes(
            (string) ($event['eventType'] ?? $event['notificationType'] ?? '')
        );

        if ($type === null) {
            return null;
        }

        $mail = $event['mail'] ?? [];
        $messageId = (string) ($mail['messageId'] ?? '');
        $occurredAt = $this->occurredAt($event, $mail);

        foreach ($this->rowsFor($event, $type, $mail) as $row) {
            // Suppression must never depend on the message id. A bounce is a
            // bounce even if SES sends a payload we cannot attribute to a
            // message we recorded, and dropping it would poison our reputation.
            $this->suppressIfNeeded($type, $occurredAt, $row);

            if ($messageId !== '') {
                $this->store($messageId, $type, $occurredAt, $row);
            }
        }

        if ($messageId !== '') {
            EmailMessage::query()
                ->where('message_id', $messageId)
                ->first()
                ?->applyEvent($type, $occurredAt);
        }

        return $type;
    }

    /**
     * @param  array{recipient: string, subtype: ?string, diagnostic: ?string, payload: array<string, mixed>}  $row
     */
    private function store(string $messageId, EmailEventType $type, Carbon $occurredAt, array $row): void
    {
        // SNS delivers at least once, so a redelivery must not double-count.
        EmailEvent::query()->updateOrCreate(
            [
                'message_id' => $messageId,
                'type' => $type,
                'recipient' => $row['recipient'],
                'occurred_at' => $occurredAt,
            ],
            [
                'subtype' => $row['subtype'],
                'diagnostic' => $row['diagnostic'],
                'payload' => $row['payload'],
            ],
        );
    }

    /**
     * @param  array{recipient: string, subtype: ?string, diagnostic: ?string, payload: array<string, mixed>}  $row
     */
    private function suppressIfNeeded(EmailEventType $type, Carbon $occurredAt, array $row): void
    {
        if ($row['recipient'] === '') {
            return;
        }

        // Transient bounces are full mailboxes and throttling, not dead
        // addresses. Suppressing them would silently drop deliverable mail.
        $isPermanentBounce = $type === EmailEventType::Bounce
            && ($row['payload']['bounceType'] ?? null) === 'Permanent';

        $reason = match (true) {
            $isPermanentBounce => EmailSuppressionReason::Bounce,
            $type === EmailEventType::Complaint => EmailSuppressionReason::Complaint,
            default => null,
        };

        if ($reason === null) {
            return;
        }

        EmailSuppression::suppress(
            email: $row['recipient'],
            reason: $reason,
            subtype: $row['subtype'],
            suppressedAt: $occurredAt,
            payload: $row['payload'],
        );
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $mail
     * @return list<array{recipient: string, subtype: ?string, diagnostic: ?string, payload: array<string, mixed>}>
     */
    private function rowsFor(array $event, EmailEventType $type, array $mail): array
    {
        return match ($type) {
            EmailEventType::Bounce => $this->recipientRows(
                $event['bounce']['bouncedRecipients'] ?? [],
                $event['bounce']['bounceSubType'] ?? null,
                $event['bounce'] ?? [],
            ),
            EmailEventType::Complaint => $this->recipientRows(
                $event['complaint']['complainedRecipients'] ?? [],
                $event['complaint']['complaintFeedbackType'] ?? null,
                $event['complaint'] ?? [],
            ),
            EmailEventType::DeliveryDelay => $this->recipientRows(
                $event['deliveryDelay']['delayedRecipients'] ?? [],
                $event['deliveryDelay']['delayType'] ?? null,
                $event['deliveryDelay'] ?? [],
            ),
            EmailEventType::Delivery => $this->plainRecipientRows(
                $event['delivery']['recipients'] ?? [],
                $event['delivery']['smtpResponse'] ?? null,
                $event['delivery'] ?? [],
            ),
            EmailEventType::Reject => [
                $this->row('', null, $event['reject']['reason'] ?? null, $event['reject'] ?? []),
            ],
            EmailEventType::RenderingFailure => [
                $this->row('', null, $event['failure']['errorMessage'] ?? null, $event['failure'] ?? []),
            ],
            default => $this->plainRecipientRows($mail['destination'] ?? [], null, []),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $recipients
     * @param  array<string, mixed>  $payload
     * @return list<array{recipient: string, subtype: ?string, diagnostic: ?string, payload: array<string, mixed>}>
     */
    private function recipientRows(array $recipients, ?string $subtype, array $payload): array
    {
        $rows = [];

        foreach ($recipients as $recipient) {
            $address = $recipient['emailAddress'] ?? null;

            if (! is_string($address) || $address === '') {
                continue;
            }

            $rows[] = $this->row(
                EmailSuppression::normalize($address),
                $subtype,
                $recipient['diagnosticCode'] ?? null,
                $payload,
            );
        }

        return $rows === [] ? [$this->row('', $subtype, null, $payload)] : $rows;
    }

    /**
     * @param  array<int, mixed>  $addresses
     * @param  array<string, mixed>  $payload
     * @return list<array{recipient: string, subtype: ?string, diagnostic: ?string, payload: array<string, mixed>}>
     */
    private function plainRecipientRows(array $addresses, ?string $diagnostic, array $payload): array
    {
        $rows = [];

        foreach ($addresses as $address) {
            if (! is_string($address) || $address === '') {
                continue;
            }

            $rows[] = $this->row(EmailSuppression::normalize($address), null, $diagnostic, $payload);
        }

        return $rows === [] ? [$this->row('', null, $diagnostic, $payload)] : $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{recipient: string, subtype: ?string, diagnostic: ?string, payload: array<string, mixed>}
     */
    private function row(string $recipient, ?string $subtype, ?string $diagnostic, array $payload): array
    {
        return [
            'recipient' => $recipient,
            'subtype' => $subtype,
            'diagnostic' => $diagnostic,
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $mail
     */
    private function occurredAt(array $event, array $mail): Carbon
    {
        $candidates = [
            $event['bounce']['timestamp'] ?? null,
            $event['complaint']['timestamp'] ?? null,
            $event['delivery']['timestamp'] ?? null,
            $event['deliveryDelay']['timestamp'] ?? null,
            $mail['timestamp'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                try {
                    return Carbon::parse($candidate);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return Carbon::now();
    }
}
