<?php

namespace App\Enums;

enum AdjustmentKind: string
{
    case Discount = 'discount';
    case Penalty = 'penalty';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Discount',
            self::Penalty => 'Penalty',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Discount => 'success',
            self::Penalty => 'warning',
        };
    }

    public function sign(): int
    {
        return match ($this) {
            self::Discount => -1,
            self::Penalty => 1,
        };
    }
}
