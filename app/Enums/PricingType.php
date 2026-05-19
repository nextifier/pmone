<?php

namespace App\Enums;

enum PricingType: string
{
    case Flat = 'flat';
    case Dynamic = 'dynamic';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat Rate',
            self::Dynamic => 'Dynamic (per range)',
        };
    }
}
