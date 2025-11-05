<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles
{
    public function __construct(
        protected ?array $filters = null,
        protected ?string $sort = null
    ) {}

    /**
     * Get the base query for the export.
     */
    abstract protected function getQuery(): Builder;

    /**
     * Get the headings for the export.
     */
    abstract public function headings(): array;

    /**
     * Map the model to an array for export.
     */
    abstract public function map($model): array;

    /**
     * Apply filters to the query.
     */
    abstract protected function applyFilters(Builder $query): void;

    /**
     * Apply sorting to the query.
     */
    abstract protected function applySorting(Builder $query): void;

    /**
     * Get the collection to export.
     */
    public function collection()
    {
        $query = $this->getQuery();

        // Apply filters if provided
        if ($this->filters) {
            $this->applyFilters($query);
        }

        // Apply sorting if provided
        if ($this->sort) {
            $this->applySorting($query);
        }

        return $query->get();
    }

    /**
     * Get the column letters that should be formatted as phone numbers.
     * Override this method to specify phone number columns.
     *
     * @return array Column letters (e.g., ['F', 'G'])
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

    /**
     * Helper method to apply search filter across multiple fields.
     */
    protected function applySearchFilter(Builder $query, array $fields, string $searchTerm): void
    {
        $searchTerm = strtolower($searchTerm);
        $query->where(function ($q) use ($fields, $searchTerm) {
            foreach ($fields as $field) {
                $q->orWhereRaw('LOWER('.$field.') LIKE ?', ["%{$searchTerm}%"]);
            }
        });
    }

    /**
     * Helper method to apply status filter.
     */
    protected function applyStatusFilter(Builder $query, string $statusFilter): void
    {
        $statuses = array_map('strtolower', explode(',', $statusFilter));
        $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), $statuses);
    }

    /**
     * Helper method to parse sort field and direction.
     */
    protected function parseSortField(string $sortField): array
    {
        $direction = str_starts_with($sortField, '-') ? 'desc' : 'asc';
        $field = ltrim($sortField, '-');

        return [$field, $direction];
    }

    /**
     * Helper method to format text with title case (capitalize each word).
     * Useful for Roles, Gender, Status, Visibility columns.
     */
    protected function titleCase(?string $value): string
    {
        if (! $value || $value === '-') {
            return $value ?: '-';
        }

        // Replace underscores with spaces, then apply title case
        return \Illuminate\Support\Str::title(str_replace('_', ' ', $value));
    }
}
