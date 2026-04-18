<?php

namespace App\Enums;

enum IdentityType: string
{
    case Nik = 'nik';
    case Passport = 'passport';

    public function label(): string
    {
        return match ($this) {
            self::Nik => 'NIK (KTP)',
            self::Passport => 'Passport',
        };
    }
}
