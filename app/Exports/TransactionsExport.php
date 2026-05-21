<?php

namespace App\Exports;

use App\DTOs\Payment\TransactionEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports payment-provider transactions to Excel. Extends BaseExport so the
 * file styling (Open Sans 14, bold header, money formatting) matches every
 * other export in the app. The rows come from a pre-fetched provider API
 * collection rather than a query, so collection() is overridden.
 */
class TransactionsExport extends BaseExport implements WithColumnWidths
{
    /**
     * Columns holding currency / money values (Rupiah, no decimals).
     */
    private const MONEY_COLUMNS = ['G', 'H'];

    /**
     * @param  Collection<int, TransactionEntry>  $transactions
     */
    public function __construct(private Collection $transactions)
    {
        parent::__construct();
    }

    /**
     * Rows are pre-fetched from the provider API, not a database query.
     */
    public function collection()
    {
        return $this->transactions;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,  // Transaction ID
            'B' => 20,  // Date
            'C' => 16,  // Type
            'D' => 14,  // Status
            'E' => 18,  // Channel
            'F' => 26,  // Reference
            'G' => 18,  // Amount
            'H' => 18,  // Net Amount
            'I' => 12,  // Currency
        ];
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'Date',
            'Type',
            'Status',
            'Channel',
            'Reference',
            'Amount',
            'Net Amount',
            'Currency',
        ];
    }

    /**
     * @param  TransactionEntry  $model
     */
    public function map($model): array
    {
        return [
            $model->id,
            $model->createdAt?->format('Y-m-d H:i') ?? '-',
            $this->titleCase($model->type),
            $this->titleCase($model->status),
            $model->channelCode ?: '-',
            $model->reference ?: '-',
            (float) $model->amount,
            $model->netAmount !== null ? (float) $model->netAmount : '-',
            $model->currency,
        ];
    }

    public function columnFormats(): array
    {
        $formats = parent::columnFormats();

        foreach (self::MONEY_COLUMNS as $col) {
            $formats[$col] = '#,##0';
        }

        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = parent::styles($sheet);

        $cellFont = [
            'font' => [
                'name' => 'Open Sans',
                'size' => 14,
            ],
        ];

        foreach (self::MONEY_COLUMNS as $col) {
            $styles[$col] = $cellFont;
        }

        return $styles;
    }

    /**
     * Not used - rows are supplied via the constructor, not a query.
     */
    protected function getQuery(): Builder
    {
        throw new \LogicException('TransactionsExport is fed a pre-fetched collection.');
    }

    protected function applyFilters(Builder $query): void {}

    protected function applySorting(Builder $query): void {}
}
