<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseTemplateExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles
{
    /**
     * Get the sample data for the template.
     */
    abstract public function array(): array;

    /**
     * Get the headings for the template.
     */
    abstract public function headings(): array;

    /**
     * Get the column letters that should be formatted as phone numbers.
     * Override this method to specify phone number columns.
     *
     * @return array Column letters (e.g., ['E', 'F'])
     */
    protected function phoneColumns(): array
    {
        return [];
    }

    /**
     * Column formatting rules.
     */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->phoneColumns() as $column) {
            $formats[$column] = '+#';
        }

        return $formats;
    }

    /**
     * Styles for the spreadsheet.
     */
    public function styles(Worksheet $sheet): array
    {
        // Set default font for entire sheet
        $sheet->getParent()->getDefaultStyle()->getFont()
            ->setName('Open Sans')
            ->setSize(14);

        // Apply font style to phone columns explicitly
        $styles = [
            1 => ['font' => ['bold' => true]],
        ];

        foreach ($this->phoneColumns() as $column) {
            $styles[$column] = [
                'font' => [
                    'name' => 'Open Sans',
                    'size' => 14,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ],
            ];
        }

        return $styles;
    }
}
