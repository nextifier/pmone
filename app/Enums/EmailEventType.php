<?php

namespace App\Enums;

/**
 * Our own event vocabulary for a sent email's lifecycle. The string values are
 * lowercase, stable identifiers stored on email_messages.status and
 * email_events.type; Resend's webhook and list payloads are mapped onto them.
 */
enum EmailEventType: string
{
    case Send = 'send';
    case Delivery = 'delivery';
    case Bounce = 'bounce';
    case Complaint = 'complaint';
    case Reject = 'reject';
    case DeliveryDelay = 'delivery_delay';
    case Open = 'open';
    case Click = 'click';

    /**
     * Maps a Resend webhook "type" (e.g. "email.bounced") onto our own event
     * vocabulary. Resend types this application does not track (scheduled,
     * received, suppressed, and the domain/contact events) return null.
     */
    public static function fromResend(string $type): ?self
    {
        return match ($type) {
            'email.sent' => self::Send,
            'email.delivered' => self::Delivery,
            'email.delivery_delayed' => self::DeliveryDelay,
            'email.bounced' => self::Bounce,
            'email.complained' => self::Complaint,
            'email.opened' => self::Open,
            'email.clicked' => self::Click,
            'email.failed' => self::Reject,
            default => null,
        };
    }

    /**
     * Maps a bare Resend "last_event" value from the list/get email API (e.g.
     * "delivered", "bounced") onto our event vocabulary. Values with no lifecycle
     * meaning for our status ranking (queued, scheduled, canceled) return null.
     */
    public static function fromResendLastEvent(string $lastEvent): ?self
    {
        return match ($lastEvent) {
            'sent' => self::Send,
            'delivered' => self::Delivery,
            'delivery_delayed' => self::DeliveryDelay,
            'bounced' => self::Bounce,
            'complained' => self::Complaint,
            'opened' => self::Open,
            'clicked' => self::Click,
            'failed' => self::Reject,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Send => 'Sent',
            self::Delivery => 'Delivered',
            self::Bounce => 'Bounced',
            self::Complaint => 'Complaint',
            self::Reject => 'Failed',
            self::DeliveryDelay => 'Delayed',
            self::Open => 'Opened',
            self::Click => 'Clicked',
        };
    }

    /**
     * How authoritative this event is about the final fate of a message. A
     * delivery must not overwrite a later bounce, and nothing overwrites a
     * complaint, so status updates only move upwards.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Send => 1,
            self::DeliveryDelay => 2,
            self::Open, self::Click => 3,
            self::Delivery => 4,
            self::Reject => 5,
            self::Bounce => 6,
            self::Complaint => 7,
        };
    }
}
