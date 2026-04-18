<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case VoucherSent = 'voucher_sent';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Paid => 'Paid',
            self::VoucherSent => 'Voucher Sent',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Paid => 'info',
            self::VoucherSent => 'success',
            self::Expired => 'muted',
            self::Cancelled => 'destructive',
            self::Refunded => 'destructive',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled, self::Refunded]);
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::VoucherSent]);
    }
}
