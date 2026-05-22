<?php

namespace App\Exports;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReservationsExport extends BaseExport implements WithColumnWidths, WithEvents
{
    /**
     * Columns that hold currency / money values (Rupiah, no decimals).
     */
    private const MONEY_COLUMNS = ['R', 'S', 'T', 'U', 'V', 'X', 'Y', 'Z', 'AI'];

    /**
     * Column holding the guest identity number. NIK is 16 digits which exceeds
     * Excel's 15-digit numeric precision, so we must force the column to text.
     */
    private const IDENTITY_NUMBER_COLUMN = 'L';

    protected function getQuery(): Builder
    {
        return Reservation::query()
            ->with(['hotel', 'event', 'creator', 'items.roomType', 'transfers'])
            ->orderByDesc('created_at');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,  // Reservation Number
            'B' => 18,  // Created At
            'C' => 20,  // Created By
            'D' => 16,  // Status
            'E' => 14,  // Source
            'F' => 28,  // Event
            'G' => 28,  // Hotel
            'H' => 24,  // Guest Name
            'I' => 30,  // Guest Email
            'J' => 18,  // Guest Phone
            'K' => 16,  // Identity Type
            'L' => 22,  // Identity Number
            'M' => 14,  // Nationality
            'N' => 22,  // Company
            'O' => 12,  // Total Nights
            'P' => 45,  // Rooms Detail
            'Q' => 35,  // Transfers
            'R' => 16,  // Subtotal Rooms
            'S' => 18,  // Subtotal Transfer
            'T' => 14,  // Surcharge
            'U' => 14,  // Penalty
            'V' => 14,  // Discount
            'W' => 16,  // Promo Code
            'X' => 14,  // Tax
            'Y' => 14,  // Service
            'Z' => 16,  // Total Amount
            'AA' => 18, // Payment Method
            'AB' => 18, // Payment Channel
            'AC' => 22, // Payment Destination
            'AD' => 26, // Xendit Invoice ID
            'AE' => 18, // Paid At
            'AF' => 18, // Voucher Sent At
            'AG' => 18, // Cancelled At
            'AH' => 35, // Cancellation Reason
            'AI' => 16, // Refund Amount
            'AJ' => 35, // Refund Reason
            'AK' => 35, // Special Request
            'AL' => 35, // Notes
        ];
    }

    public function headings(): array
    {
        return [
            'Reservation Number',
            'Created At',
            'Created By',
            'Status',
            'Source',
            'Event',
            'Hotel',
            'Guest Name',
            'Guest Email',
            'Guest Phone',
            'Identity Type',
            'Identity Number',
            'Nationality',
            'Company',
            'Total Nights',
            'Rooms Detail',
            'Transfers',
            'Subtotal Rooms',
            'Subtotal Transfer',
            'Surcharge',
            'Penalty',
            'Discount',
            'Promo Code',
            'Tax',
            'Service',
            'Total Amount',
            'Payment Method',
            'Payment Channel',
            'Payment Destination',
            'Xendit Invoice ID',
            'Paid At',
            'Voucher Sent At',
            'Cancelled At',
            'Cancellation Reason',
            'Refund Amount',
            'Refund Reason',
            'Special Request',
            'Notes',
        ];
    }

    public function map($model): array
    {
        $totalNights = (int) $model->items->sum('nights');

        $roomsDetail = $model->items->map(fn ($item) => sprintf(
            '%s x%d (%s-%s)',
            $item->roomType?->name,
            $item->qty,
            Carbon::parse($item->check_in_date)->format('d/m'),
            Carbon::parse($item->check_out_date)->format('d/m'),
        ))->join(' | ');

        $transfersDetail = $model->transfers->map(fn ($t) => sprintf(
            '%s %s (%d pax)',
            $t->direction?->label() ?? '-',
            Carbon::parse($t->transfer_date)->format('d/m'),
            $t->pax_count,
        ))->join(' | ');

        return [
            $model->reservation_number,
            $model->created_at?->format('Y-m-d H:i'),
            $model->creator?->name ?? '-',
            $model->status?->label(),
            $model->source?->label(),
            $model->event?->title ?? '-',
            $model->hotel?->name ?? '-',
            $model->guest_name,
            $model->guest_email,
            $this->cleanPhone($model->guest_phone),
            $model->guest_identity_type?->label(),
            $model->guest_identity_number,
            $model->guest_nationality ?? '-',
            $model->guest_company ?? '-',
            $totalNights,
            $roomsDetail ?: '-',
            $transfersDetail ?: '-',
            (float) $model->subtotal_rooms,
            (float) $model->subtotal_transfer,
            (float) $model->surcharge_amount,
            (float) $model->penalty_amount,
            (float) $model->discount_amount,
            $model->promo_code_applied ?: '-',
            (float) $model->tax_amount,
            (float) $model->service_charge_amount,
            (float) $model->total_amount,
            $model->payment_method?->label() ?? '-',
            $model->payment_channel ?: '-',
            $model->payment_destination ?: '-',
            $model->xendit_invoice_id ?: '-',
            $model->paid_at?->format('Y-m-d H:i') ?? '-',
            $model->voucher_sent_at?->format('Y-m-d H:i') ?? '-',
            $model->cancelled_at?->format('Y-m-d H:i') ?? '-',
            $model->cancellation_reason ?: '-',
            $model->refund_amount !== null ? (float) $model->refund_amount : '-',
            $model->refund_reason ?: '-',
            $model->special_request ?: '-',
            $model->notes ?: '-',
        ];
    }

    protected function phoneColumns(): array
    {
        return ['J'];
    }

    public function columnFormats(): array
    {
        $formats = parent::columnFormats();

        $formats[self::IDENTITY_NUMBER_COLUMN] = NumberFormat::FORMAT_TEXT;

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

        $styles[self::IDENTITY_NUMBER_COLUMN] = $cellFont + [
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ];

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highest = $sheet->getHighestRow();

                for ($row = 2; $row <= $highest; $row++) {
                    $cell = $sheet->getCell(self::IDENTITY_NUMBER_COLUMN.$row);
                    $value = $cell->getValue();

                    if ($value === null || $value === '' || $value === '-') {
                        continue;
                    }

                    $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
                }
            },
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        if ($search = $this->filters['search'] ?? null) {
            $this->applySearchFilter($query, ['reservation_number', 'guest_name', 'guest_email'], $search);
        }

        if ($status = $this->filters['status'] ?? null) {
            $this->applyStatusFilter($query, is_array($status) ? implode(',', $status) : $status);
        }

        if ($paymentChannel = $this->filters['payment_channel'] ?? null) {
            $channels = array_filter(is_array($paymentChannel) ? $paymentChannel : explode(',', $paymentChannel));
            if ($channels) {
                $query->whereIn('payment_channel', $channels);
            }
        }

        if ($mode = $this->filters['mode'] ?? null) {
            $modes = array_filter(is_array($mode) ? $mode : explode(',', $mode));
            if ($modes) {
                $query->whereHas('paymentGateway', fn ($q) => $q->whereIn('mode', $modes));
            }
        }

        if ($eventId = $this->filters['event_id'] ?? null) {
            $query->where('event_id', $eventId);
        }

        if ($hotelId = $this->filters['hotel_id'] ?? null) {
            $query->where('hotel_id', $hotelId);
        }

        if ($from = $this->filters['date_from'] ?? null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $this->filters['date_to'] ?? null) {
            $query->where('created_at', '<=', $to);
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        $allowed = ['reservation_number', 'created_at', 'paid_at', 'total_amount', 'guest_name', 'status'];

        if (in_array($field, $allowed, true)) {
            $query->reorder($field, $direction);
        }
    }

    private function cleanPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '-';
        }

        return preg_replace('/[^\d]/', '', $phone) ?: '-';
    }
}
