<?php

namespace App\Exports;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReservationsExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return Reservation::query()
            ->with(['hotel', 'event', 'items.roomType', 'transfers'])
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Reservation Number',
            'Created At',
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
            'Check-in Earliest',
            'Check-out Latest',
            'Total Nights',
            'Rooms Detail',
            'Transfers',
            'Subtotal Rooms',
            'Subtotal Transfer',
            'Tax',
            'Service',
            'Total Amount',
            'Payment Method',
            'Paid At',
            'Voucher Sent At',
            'Cancelled At',
            'Refund Amount',
        ];
    }

    public function map($model): array
    {
        $earliest = $model->items->min('check_in_date');
        $latest = $model->items->max('check_out_date');
        $totalNights = $model->items->sum('nights');

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
            $model->status?->label(),
            $model->source?->label(),
            $model->event?->title ?? '-',
            $model->hotel?->name ?? '-',
            $model->guest_name,
            $model->guest_email,
            $model->guest_phone,
            $model->guest_identity_type?->label(),
            $model->guest_identity_number,
            $model->guest_nationality ?? '-',
            $model->guest_company ?? '-',
            $earliest ?? '-',
            $latest ?? '-',
            $totalNights,
            $roomsDetail ?: '-',
            $transfersDetail ?: '-',
            (float) $model->subtotal_rooms,
            (float) $model->subtotal_transfer,
            (float) $model->tax_amount,
            (float) $model->service_charge_amount,
            (float) $model->total_amount,
            $model->payment_method?->label() ?? '-',
            $model->paid_at?->format('Y-m-d H:i') ?? '-',
            $model->voucher_sent_at?->format('Y-m-d H:i') ?? '-',
            $model->cancelled_at?->format('Y-m-d H:i') ?? '-',
            $model->refund_amount !== null ? (float) $model->refund_amount : '-',
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
}
