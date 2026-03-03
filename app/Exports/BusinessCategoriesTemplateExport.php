<?php

namespace App\Exports;

class BusinessCategoriesTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            ['Building Materials'],
            ['Interior Design'],
            ['Electrical & Lighting'],
            [''],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
        ];
    }
}
