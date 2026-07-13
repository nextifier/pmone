<?php

namespace App\Enums;

/**
 * The event types SES publishes to a configuration set destination. The string
 * values are SES's own "eventType" values, lowercased, so mapping stays trivial.
 */
enum EmailEventType: string
{
    case Send = 'send';
    case Delivery = 'delivery';
    case Bounce = 'bounce';
    case Complaint = 'complaint';
    case Reject = 'reject';
    case DeliveryDelay = 'delivery_delay';
    case RenderingFailure = 'rendering_failure';
    case Open = 'open';
    case Click = 'click';

    public static function fromSes(string $eventType): ?self
    {
        return match ($eventType) {
            'Send' => self::Send,
            'Delivery' => self::Delivery,
            'Bounce' => self::Bounce,
            'Complaint' => self::Complaint,
            'Reject' => self::Reject,
            'DeliveryDelay' => self::DeliveryDelay,
            'Rendering Failure' => self::RenderingFailure,
            'Open' => self::Open,
            'Click' => self::Click,
            default => null,
        };
    }

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

    public function label(): string
    {
        return match ($this) {
            self::Send => 'Sent',
            self::Delivery => 'Delivered',
            self::Bounce => 'Bounced',
            self::Complaint => 'Complaint',
            self::Reject => 'Rejected',
            self::DeliveryDelay => 'Delayed',
            self::RenderingFailure => 'Rendering failure',
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
            self::RenderingFailure, self::Reject => 5,
            self::Bounce => 6,
            self::Complaint => 7,
        };
    }
}
