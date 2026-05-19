<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Xendit = 'xendit';
    case ManualBankTransfer = 'manual_bank_transfer';
    case Complimentary = 'complimentary';

    public function label(): string
    {
        return match ($this) {
            // `Xendit` is the gateway, not the channel the guest actually paid
            // with. Receipts and admin tables should never surface "Xendit" as
            // the payment method — fall back to the neutral "Online Payment"
            // when the specific channel (BCA, OVO, etc.) is missing.
            self::Xendit => 'Online Payment',
            self::ManualBankTransfer => 'Manual Bank Transfer',
            self::Complimentary => 'Complimentary',
        };
    }
}
