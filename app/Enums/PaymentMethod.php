<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Xendit = 'xendit';
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
            self::ManualBankTransfer => 'Manual Bank Transfer',
            self::Complimentary => 'Complimentary',
        };
    }
}
