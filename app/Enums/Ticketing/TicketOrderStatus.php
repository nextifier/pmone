<?php

namespace App\Enums\Ticketing;

/**
 * Lifecycle of a ticket order. Free orders skip the gateway and land on
 * Confirmed directly; paid orders move PendingPayment -> Confirmed on a paid
 * webhook, or -> Expired on timeout. Tickets are only valid once Confirmed.
 */
enum TicketOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Confirmed => 'success',
            self::Cancelled => 'destructive',
            self::Expired => 'muted',
        };
    }

    /**
     * Tickets are only redeemable while the order is Confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * Terminal states the order can no longer leave.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Confirmed, self::Cancelled, self::Expired], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
