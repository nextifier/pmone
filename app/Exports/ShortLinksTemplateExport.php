<?php

namespace App\Exports;

class ShortLinksTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'my-link',
                'https://example.com',
                'Active',
            ],
            [
                'promo-2024',
                'https://example.com/promo',
                'Active',
            ],
            [
                'disabled-link',
                'https://example.com/old',
                'Inactive',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Slug',
            'Destination URL',
            'Status',
        ];
    }
}
