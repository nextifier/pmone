<?php

namespace App\Exports;

class BrandEventsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'Brand A',
                'PT Brand A Indonesia',
                'brand-a@example.com',
                '628123456789',
                'pic@example.com',
                'A-01',
                '9',
                'Raw Space',
                'Active',
            ],
            [
                'Brand B',
                'CV Brand B',
                'brand-b@example.com',
                '628987654321',
                'john@example.com',
                'B-02',
                '6',
                'Standard Shell Scheme',
                'Active',
            ],
            [
                'Brand C',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Brand Name',
            'Company Name',
            'Company Email',
            'Company Phone',
            'PIC Email',
            'Booth Number',
            'Booth Size (sqm)',
            'Booth Type',
            'Status',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
