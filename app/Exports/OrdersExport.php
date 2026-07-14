<?php

namespace App\Exports;

use App\Models\BrandEvent;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport extends BaseExport
{
    public function __construct(
        protected int $eventId,
        protected ?array $filters = null,
        protected ?string $sort = null
    ) {}

    protected function getQuery(): Builder
    {
        return Order::query()
            ->whereIn('brand_event_id', BrandEvent::where('event_id', $this->eventId)->select('id'))
            ->with(['brandEvent.brand', 'brandEvent.sales', 'items.productCategory', 'creator'])
            ->withCount('items');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Order Number',
            'Brand Name',
            'Company Name',
            'Booth Type',
            'Booth Number',
            'Booth Size (sqm)',
            'Booth Price',
            'Fascia Name',
            'Badge Name',
            'Sales PIC',
            'Order Period',
            'Product Name',
            'Product Category',
            'Qty',
            'Unit Price',
            'Item Total',
            'Item Notes',
            'Subtotal',
            'Discount Amount',
            'Penalty Amount',
            'Promo Code',
            'Tax Rate (%)',
            'Tax Amount',
            'Total',
            'Operational Status',
            'Payment Status',
            'Cancellation Reason',
            'Order Notes',
            'Submitted At',
            'Confirmed At',
            'Created By',
            'Currency',
            'Exchange Rate (to IDR)',
            'Total (IDR)',
        ];
    }

    /**
     * @param  Order  $order
     * @return array<int, array<int, mixed>>
     */
    public function map($order): array
    {
        $brand = $order->brandEvent?->brand;
        $brandEvent = $order->brandEvent;
        $items = $order->items;

        $orderFields = [
            $order->id,
            $order->order_number,
            $brand?->name ?? '-',
            $brand?->company_name ?? '-',
            $brandEvent?->booth_type?->label() ?? '-',
            $brandEvent?->booth_number ?? '-',
            $brandEvent?->booth_size,
            $brandEvent?->booth_price,
            $brandEvent?->fascia_name ?? '-',
            $brandEvent?->badge_name ?? '-',
            $brandEvent?->sales?->name ?? '-',
            $order->order_period ? $this->titleCase($order->order_period) : '-',
        ];

        $orderSummary = [
            $order->subtotal,
            $order->discount_amount,
            $order->penalty_amount,
            $order->promo_code_applied ?? '-',
            $order->tax_rate,
            $order->tax_amount,
            $order->total,
            $order->operational_status?->label() ?? '-',
            $order->payment_status?->label() ?? '-',
            $order->cancellation_reason,
            $order->notes,
            $order->submitted_at?->format('Y-m-d H:i:s'),
            $order->confirmed_at?->format('Y-m-d H:i:s'),
            $order->creator?->name ?? '-',
            $order->currency ?? 'IDR',
            (float) $order->exchange_rate_to_idr,
            (float) $order->total_idr,
        ];

        if ($items->isEmpty()) {
            return [array_merge($orderFields, ['-', '-', 0, 0, 0, '-'], $orderSummary)];
        }

        return $items->map(fn ($item) => array_merge(
            $orderFields,
            [
                $item->product_name,
                $item->productCategory?->title ?? '-',
                $item->quantity,
                $item->unit_price,
                $item->total_price,
                $item->notes,
            ],
            $orderSummary,
        ))->toArray();
    }

    /**
     * Number columns (after inserting "Badge Name" at col J): G=Booth Size,
     * H=Booth Price, O=Qty, P=Unit Price, Q=Item Total, S=Subtotal,
     * T=Discount Amount, U=Penalty Amount, W=Tax Rate, X=Tax Amount, Y=Total,
     * AH=Exchange Rate (to IDR), AI=Total (IDR).
     */
    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00',
            'H' => '#,##0',
            'O' => '#,##0',
            'P' => '#,##0',
            'Q' => '#,##0',
            'S' => '#,##0',
            'T' => '#,##0',
            'U' => '#,##0',
            'W' => '#,##0.00',
            'X' => '#,##0',
            'Y' => '#,##0',
            'AH' => '#,##0.000000',
            'AI' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = parent::styles($sheet);

        $numberFont = [
            'font' => [
                'name' => 'Open Sans',
                'size' => 14,
            ],
        ];

        foreach (['G', 'H', 'O', 'P', 'Q', 'S', 'T', 'U', 'W', 'X', 'Y', 'AH', 'AI'] as $column) {
            $styles[$column] = $numberFont;
        }

        return $styles;
    }

    protected function applyFilters(Builder $query): void
    {
        if (isset($this->filters['search'])) {
            $search = strtolower($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(order_number) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('brandEvent.brand', function ($q) use ($search) {
                        $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(company_name) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if (isset($this->filters['operational_status'])) {
            $statuses = explode(',', $this->filters['operational_status']);
            $query->whereIn('operational_status', $statuses);
        }

        if (isset($this->filters['payment_status'])) {
            $statuses = explode(',', $this->filters['payment_status']);
            $query->whereIn('payment_status', $statuses);
        }

        if (isset($this->filters['currency'])) {
            $query->whereIn('currency', explode(',', $this->filters['currency']));
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort ?? '-submitted_at');

        if (in_array($field, ['order_number', 'total', 'operational_status', 'payment_status', 'submitted_at', 'created_at'])) {
            $query->orderBy($field, $direction);
        } else {
            $query->orderByDesc('submitted_at');
        }
    }
}
