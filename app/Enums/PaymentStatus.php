<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case NotInvoiced = 'not_invoiced';
    case Invoiced = 'invoiced';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::NotInvoiced => 'Not Invoiced',
            self::Invoiced => 'Unpaid',
            self::Paid => 'Paid',
        };
    }
}
