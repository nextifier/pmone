<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PropertyAnalyticsExport implements WithMultipleSheets
{
    public function __construct(
        protected array $data,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function sheets(): array
    {
        return [
            new PropertyAnalyticsSummarySheet($this->data, $this->startDate, $this->endDate),
            new PropertyAnalyticsDailySheet($this->data, $this->startDate, $this->endDate),
            new PropertyAnalyticsTopPagesSheet($this->data, $this->startDate, $this->endDate),
            new PropertyAnalyticsTrafficSourcesSheet($this->data, $this->startDate, $this->endDate),
        ];
    }
}

/**
 * Summary sheet with property metrics
 */
class PropertyAnalyticsSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected array $data,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function title(): string
    {
        return 'Summary';
    }

    public function headings(): array
    {
        return [
            'Metric',
            'Value',
        ];
    }

    public function array(): array
    {
        $property = $this->data['property'] ?? [];
        $metrics = $this->data['metrics'] ?? [];

        return [
            ['Property Name', $property['name'] ?? 'N/A'],
            ['Property ID', $property['property_id'] ?? 'N/A'],
            ['Project', $property['project']['name'] ?? 'N/A'],
            ['Period', "{$this->startDate} to {$this->endDate}"],
            [''],
            ['Active Users', $metrics['activeUsers'] ?? 0],
            ['Total Users', $metrics['totalUsers'] ?? 0],
            ['New Users', $metrics['newUsers'] ?? 0],
            ['Sessions', $metrics['sessions'] ?? 0],
            ['Page Views', $metrics['screenPageViews'] ?? 0],
            ['Bounce Rate', $this->formatPercent($metrics['bounceRate'] ?? 0)],
            ['Average Session Duration', $this->formatDuration($metrics['averageSessionDuration'] ?? 0)],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            6 => ['font' => ['bold' => true]],
        ];
    }

    protected function formatPercent(float $value): string
    {
        return number_format($value * 100, 2).'%';
    }

    protected function formatDuration(float $seconds): string
    {
        $minutes = floor($seconds / 60);
        $secs = floor($seconds % 60);

        return "{$minutes}m {$secs}s";
    }
}

/**
 * Daily data sheet with time series
 */
class PropertyAnalyticsDailySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected array $data,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function title(): string
    {
        return 'Daily Data';
    }

    public function headings(): array
    {
        return [
            'Date',
            'Active Users',
            'Total Users',
            'New Users',
            'Sessions',
            'Page Views',
        ];
    }

    public function array(): array
    {
        $rows = $this->data['rows'] ?? [];

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                $row['date'] ?? '',
                $row['activeUsers'] ?? 0,
                $row['totalUsers'] ?? 0,
                $row['newUsers'] ?? 0,
                $row['sessions'] ?? 0,
                $row['screenPageViews'] ?? 0,
            ];
        }

        return $result;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

/**
 * Top pages sheet
 */
class PropertyAnalyticsTopPagesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected array $data,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function title(): string
    {
        return 'Top Pages';
    }

    public function headings(): array
    {
        return [
            'Rank',
            'Page Path',
            'Page Views',
            'Active Users',
        ];
    }

    public function array(): array
    {
        $topPages = $this->data['top_pages'] ?? [];

        $rows = [];
        foreach ($topPages as $index => $page) {
            $rows[] = [
                $index + 1,
                $page['pagePath'] ?? '',
                $page['screenPageViews'] ?? 0,
                $page['activeUsers'] ?? 0,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

/**
 * Traffic sources sheet
 */
class PropertyAnalyticsTrafficSourcesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected array $data,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function title(): string
    {
        return 'Traffic Sources';
    }

    public function headings(): array
    {
        return [
            'Source',
            'Medium',
            'Campaign',
            'Landing Page',
            'Sessions',
            'Active Users',
            'Bounce Rate',
            'Avg. Duration (s)',
        ];
    }

    public function array(): array
    {
        $trafficSources = $this->data['traffic_sources'] ?? [];

        $rows = [];
        foreach ($trafficSources as $source) {
            $bounceRate = $source['bounce_rate'] ?? 0;

            $rows[] = [
                $source['source'] ?? $source['sessionSource'] ?? '(direct)',
                $source['medium'] ?? $source['sessionMedium'] ?? '(none)',
                $source['campaign'] ?? '(not set)',
                $source['landing_page'] ?? '(not set)',
                $source['sessions'] ?? 0,
                $source['users'] ?? $source['activeUsers'] ?? 0,
                round($bounceRate * 100, 1).'%',
                round($source['avg_duration'] ?? 0, 1),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
