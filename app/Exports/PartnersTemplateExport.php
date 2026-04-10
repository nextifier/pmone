<?php

namespace App\Exports;

class PartnersTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'Detik',
                'https://detik.com',
                'Active',
                'Public',
            ],
            [
                'Kompas',
                'https://kompas.com',
                'Active',
                'Public',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Website URL',
            'Status',
            'Visibility',
        ];
    }
}
