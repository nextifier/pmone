<?php

namespace App\Enums;

enum TransferDirection: string
{
    case In = 'in';
    case Out = 'out';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Arrival (In)',
            self::Out => 'Departure (Out)',
            self::Both => 'Both Ways',
        };
    }
}
