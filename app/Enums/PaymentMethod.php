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
            self::Xendit => 'Xendit',
            self::ManualBankTransfer => 'Manual Bank Transfer',
            self::Complimentary => 'Complimentary',
        };
    }
}
