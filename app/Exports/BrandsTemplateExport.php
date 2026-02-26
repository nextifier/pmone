<?php

namespace App\Exports;

class BrandsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'Levenium',
                'Levenium Lab',
                'hello@levenium.com',
                '+6281234567890',
                'Jl. Sudirman No. 1, Jakarta',
                'Active',
                'Public',
                'Technology, Digital',
            ],
            [
                'Green Earth',
                'Green Earth Indonesia',
                'info@greenearth.id',
                '+6289876543210',
                'Jl. Gatot Subroto No. 5, Bandung',
                'Active',
                'Public',
                'Sustainability, Agriculture',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Company Name',
            'Company Email',
            'Company Phone',
            'Company Address',
            'Status',
            'Visibility',
            'Business Categories',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
