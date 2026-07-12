<?php

namespace App\Enums\Ticketing;

/**
 * Lifecycle of a waitlist entry. `waiting` sits in the FIFO queue for a
 * sold-out ticket; a release (order expiry / refund) moves the next entries
 * to `offered` with a time-limited claim window, which resolves to either
 * `claimed` (order created for the held seat) or `expired` (window lapsed,
 * seat released back and re-offered to the next entry). `cancelled` is a
 * buyer- or staff-initiated withdrawal from the queue.
 */
enum TicketWaitlistEntryStatus: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Claimed = 'claimed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Offered => 'Offered',
            self::Claimed => 'Claimed',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Terminal states the entry can no longer leave.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Claimed, self::Expired, self::Cancelled], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
