<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Xendit = 'xendit';
    case Midtrans = 'midtrans';
    case ManualBankTransfer = 'manual_bank_transfer';
    case Complimentary = 'complimentary';

    public function label(): ?string
    {
        return match ($this) {
            // `Xendit` is the gateway, not a method the guest can be shown, so
            // it has no display label of its own. A reservation paid via Xendit
            // is identified by its channel (BCA, QRIS, ...); when no channel is
            // known the field is left blank rather than surfacing a vague
            // "Online Payment". The Status column already conveys whether
            // payment has happened.
            self::Xendit => null,
            // Like Xendit, Midtrans is the gateway, not a guest-facing method.
            // The reservation's channel (BCA, QRIS, GoPay, ...) identifies how
            // payment happened; the field is left blank when no channel is known.
            self::Midtrans => null,
            self::ManualBankTransfer => 'Manual Bank Transfer',
            self::Complimentary => 'Complimentary',
        };
    }
}
