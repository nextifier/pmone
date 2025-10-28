<?php

namespace App\Exports;

class ProjectsTemplateExport extends BaseTemplateExport
{
    public function array(): array
    {
        return [
            [
                'Levenium',
                'levenium',
                'hello@levenium.com',
                '+6281234567890',
                'Active',
                'Public',
                'Project / brand description.',
                'https://levenium.com',
                'https://www.instagram.com/leveniumlab',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Name',
            'Username',
            'Email',
            'Phone',
            'Status',
            'Visibility',
            'Bio',
            'Website',
            'Instagram',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['D'];
    }
}
