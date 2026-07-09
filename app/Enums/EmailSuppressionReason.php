<?php

namespace App\Enums;

enum EmailSuppressionReason: string
{
    case Bounce = 'bounce';
    case Complaint = 'complaint';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Bounce => 'Bounce',
            self::Complaint => 'Complaint',
            self::Manual => 'Manual',
        };
    }
}
