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

class AggregatedAnalyticsExport implements WithMultipleSheets
{
    public function __construct(
        protected array $data,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function sheets(): array
    {
        return [
            new AggregatedAnalyticsSummarySheet($this->data, $this->startDate, $this->endDate),
            new AggregatedAnalyticsDailySheet($this->data, $this->startDate, $this->endDate),
            new AggregatedAnalyticsPropertiesSheet($this->data, $this->startDate, $this->endDate),
            new AggregatedAnalyticsTopPagesSheet($this->data, $this->startDate, $this->endDate),
            new AggregatedAnalyticsTrafficSourcesSheet($this->data, $this->startDate, $this->endDate),
        ];
    }
}

/**
 * Summary sheet with overall metrics
 */
class AggregatedAnalyticsSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
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
        $totals = $this->data['totals'] ?? [];

        return [
            ['Period', "{$this->startDate} to {$this->endDate}"],
            [''],
            ['Active Users', $totals['activeUsers'] ?? 0],
            ['Total Users', $totals['totalUsers'] ?? 0],
            ['New Users', $totals['newUsers'] ?? 0],
            ['Sessions', $totals['sessions'] ?? 0],
            ['Page Views', $totals['screenPageViews'] ?? 0],
            ['Bounce Rate', $this->formatPercent($totals['bounceRate'] ?? 0)],
            ['Average Session Duration', $this->formatDuration($totals['averageSessionDuration'] ?? 0)],
            [''],
            ['Total Properties', $this->data['properties_count'] ?? 0],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
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
class AggregatedAnalyticsDailySheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
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
        $propertyBreakdown = $this->data['property_breakdown'] ?? [];

        // Aggregate daily data from all properties
        $dailyDataMap = [];

        foreach ($propertyBreakdown as $property) {
            if (! isset($property['rows']) || ! is_array($property['rows'])) {
                continue;
            }

            foreach ($property['rows'] as $row) {
                $date = $row['date'];

                if (! isset($dailyDataMap[$date])) {
                    $dailyDataMap[$date] = [
                        'activeUsers' => 0,
                        'totalUsers' => 0,
                        'newUsers' => 0,
                        'sessions' => 0,
                        'screenPageViews' => 0,
                    ];
                }

                $dailyDataMap[$date]['activeUsers'] += $row['activeUsers'] ?? 0;
                $dailyDataMap[$date]['totalUsers'] += $row['totalUsers'] ?? 0;
                $dailyDataMap[$date]['newUsers'] += $row['newUsers'] ?? 0;
                $dailyDataMap[$date]['sessions'] += $row['sessions'] ?? 0;
                $dailyDataMap[$date]['screenPageViews'] += $row['screenPageViews'] ?? 0;
            }
        }

        // Sort by date
        ksort($dailyDataMap);

        // Convert to array format
        $rows = [];
        foreach ($dailyDataMap as $date => $metrics) {
            $rows[] = [
                $date,
                $metrics['activeUsers'],
                $metrics['totalUsers'],
                $metrics['newUsers'],
                $metrics['sessions'],
                $metrics['screenPageViews'],
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
 * Property breakdown sheet
 */
class AggregatedAnalyticsPropertiesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        protected array $data,
        protected string $startDate,
        protected string $endDate
    ) {}

    public function title(): string
    {
        return 'Properties';
    }

    public function headings(): array
    {
        return [
            'Property ID',
            'Property Name',
            'Project',
            'Active Users',
            'Total Users',
            'New Users',
            'Sessions',
            'Page Views',
            'Bounce Rate',
            'Avg. Duration (seconds)',
        ];
    }

    public function array(): array
    {
        $propertyBreakdown = $this->data['property_breakdown'] ?? [];

        $rows = [];
        foreach ($propertyBreakdown as $property) {
            $metrics = $property['metrics'] ?? [];

            $rows[] = [
                $property['property_id'] ?? '',
                $property['property_name'] ?? '',
                $property['project']['name'] ?? 'N/A',
                $metrics['activeUsers'] ?? 0,
                $metrics['totalUsers'] ?? 0,
                $metrics['newUsers'] ?? 0,
                $metrics['sessions'] ?? 0,
                $metrics['screenPageViews'] ?? 0,
                number_format(($metrics['bounceRate'] ?? 0) * 100, 2).'%',
                round($metrics['averageSessionDuration'] ?? 0),
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
 * Top pages sheet
 */
class AggregatedAnalyticsTopPagesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
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
class AggregatedAnalyticsTrafficSourcesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
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
