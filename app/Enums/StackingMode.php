<?php

namespace App\Enums;

enum StackingMode: string
{
    case Exclusive = 'exclusive';
    case CombinableWithPromo = 'combinable_with_promo';
    case CombinableWithManual = 'combinable_with_manual';
    case CombinableWithAll = 'combinable_with_all';

    public function label(): string
    {
        return match ($this) {
            self::Exclusive => 'Exclusive',
            self::CombinableWithPromo => 'Combinable with Promo',
            self::CombinableWithManual => 'Combinable with Manual',
            self::CombinableWithAll => 'Combinable with All',
        };
    }

    public function isExclusive(): bool
    {
        return $this === self::Exclusive;
    }
}
