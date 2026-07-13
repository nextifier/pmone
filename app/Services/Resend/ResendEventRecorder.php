<?php

namespace App\Services\Resend;

use App\Enums\EmailEventType;
use App\Enums\EmailSuppressionReason;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use Illuminate\Support\Carbon;

/**
 * Turns one Resend webhook notification into rows: an event per affected
 * recipient, an updated message status, and a suppression entry when the
 * address hard-bounced or its owner complained.
 *
 * Mirrors SesEventRecorder so the dashboard stays provider-agnostic: both
 * writers feed the same email_messages / email_events / email_suppressions
 * tables, keyed by the id this application already recorded at send time
 * (X-Resend-Email-ID, which Resend echoes back as data.email_id).
 */
class ResendEventRecorder
{
    /**
     * Returns the recognised event type, or null when Resend sent something
     * this application does not track.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(array $payload): ?EmailEventType
    {
        $type = EmailEventType::fromResend((string) ($payload['type'] ?? ''));

        if ($type === null) {
            return null;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $messageId = (string) ($data['email_id'] ?? '');
        $occurredAt = $this->occurredAt($payload, $data);
        [$subtype, $diagnostic] = $this->detail($type, $data);

        foreach ($this->recipients($data) as $recipient) {
            // Suppression must never depend on the message id. A bounce is a
            // bounce even if we cannot attribute it to a message we recorded,
            // and dropping it would poison our sending reputation.
            $this->suppressIfNeeded($type, $occurredAt, $recipient, $subtype, $data);

            if ($messageId !== '') {
                $this->store($messageId, $type, $recipient, $subtype, $diagnostic, $occurredAt, $data);
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
     * @param  array<string, mixed>  $data
     */
    private function store(
        string $messageId,
        EmailEventType $type,
        string $recipient,
        ?string $subtype,
        ?string $diagnostic,
        Carbon $occurredAt,
        array $data,
    ): void {
        // Resend can redeliver a webhook, so a redelivery must not double-count.
        EmailEvent::query()->updateOrCreate(
            [
                'message_id' => $messageId,
                'type' => $type,
                'recipient' => $recipient,
                'occurred_at' => $occurredAt,
            ],
            [
                'subtype' => $subtype,
                'diagnostic' => $diagnostic,
                'payload' => $data,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function suppressIfNeeded(
        EmailEventType $type,
        Carbon $occurredAt,
        string $recipient,
        ?string $subtype,
        array $data,
    ): void {
        if ($recipient === '') {
            return;
        }

        // Transient bounces are full mailboxes and throttling, not dead
        // addresses. Suppressing them would silently drop deliverable mail.
        $isPermanentBounce = $type === EmailEventType::Bounce
            && mb_strtolower((string) ($data['bounce']['type'] ?? '')) === 'permanent';

        $reason = match (true) {
            $isPermanentBounce => EmailSuppressionReason::Bounce,
            $type === EmailEventType::Complaint => EmailSuppressionReason::Complaint,
            default => null,
        };

        if ($reason === null) {
            return;
        }

        EmailSuppression::suppress(
            email: $recipient,
            reason: $reason,
            subtype: $subtype,
            suppressedAt: $occurredAt,
            payload: $data,
            source: 'resend',
        );
    }

    /**
     * Resend delivers recipients as a plain list of addresses under data.to.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function recipients(array $data): array
    {
        $to = $data['to'] ?? [];
        $to = is_array($to) ? $to : [$to];

        $rows = [];

        foreach ($to as $address) {
            if (is_string($address) && $address !== '') {
                $rows[] = EmailSuppression::normalize($address);
            }
        }

        // A payload with no addresses still produces one row so an unattributed
        // failure (e.g. email.failed) is not silently dropped.
        return $rows === [] ? [''] : $rows;
    }

    /**
     * The human-readable subtype and diagnostic vary by event: a bounce carries
     * its classification and provider message, a click carries the link.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string}
     */
    private function detail(EmailEventType $type, array $data): array
    {
        return match ($type) {
            EmailEventType::Bounce => [
                $this->stringOrNull($data['bounce']['subType'] ?? null),
                $this->stringOrNull($data['bounce']['message'] ?? null),
            ],
            EmailEventType::Complaint => [
                $this->stringOrNull($data['complaint']['type'] ?? null),
                $this->stringOrNull($data['complaint']['message'] ?? null),
            ],
            EmailEventType::Click => [null, $this->stringOrNull($data['click']['link'] ?? null)],
            EmailEventType::Reject => [null, $this->stringOrNull($data['failed']['reason'] ?? null)],
            default => [null, null],
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function occurredAt(array $payload, array $data): Carbon
    {
        $candidates = [
            $data['bounce']['timestamp'] ?? null,
            $data['click']['timestamp'] ?? null,
            $data['open']['timestamp'] ?? null,
            $data['created_at'] ?? null,
            $payload['created_at'] ?? null,
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
