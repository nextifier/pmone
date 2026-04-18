<?php

namespace App\Enums;

enum ReservationSource: string
{
    case PublicWebsite = 'public_website';
    case AdminManual = 'admin_manual';

    public function label(): string
    {
        return match ($this) {
            self::PublicWebsite => 'Public Website',
            self::AdminManual => 'Admin Manual',
        };
    }
}
