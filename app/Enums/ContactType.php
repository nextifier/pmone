<?php

namespace App\Enums;

enum ContactType: string
{
    case Exhibitor = 'exhibitor';
    case MediaPartner = 'media-partner';
    case Sponsor = 'sponsor';
    case Speaker = 'speaker';
    case Vendor = 'vendor';
    case Visitor = 'visitor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Exhibitor => 'Exhibitor',
            self::MediaPartner => 'Media Partner',
            self::Sponsor => 'Sponsor',
            self::Speaker => 'Speaker',
            self::Vendor => 'Vendor',
            self::Visitor => 'Visitor',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
