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
            'Sales PIC',
            'Order Period',
            'Product Name',
            'Product Category',
            'Qty',
            'Unit Price',
            'Item Total',
            'Item Notes',
            'Subtotal',
            'Discount Type',
            'Discount Value',
            'Discount Amount',
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
            $brandEvent?->sales?->name ?? '-',
            $order->order_period ? $this->titleCase($order->order_period) : '-',
        ];

        $orderSummary = [
            $order->subtotal,
            $order->discount_type ? $this->titleCase($order->discount_type) : '-',
            $order->discount_value,
            $order->discount_amount,
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
        ];

        if ($items->isEmpty()) {
            return [array_merge($orderFields, ['-', '-', 0, 0, 0, '-'], $orderSummary)];
        }

        return $items->map(fn ($item) => array_merge(
            $orderFields,
            [
                $item->product_name,
                $item->productCategory?->name ?? '-',
                $item->quantity,
                $item->unit_price,
                $item->total_price,
                $item->notes,
            ],
            $orderSummary,
        ))->toArray();
    }

    /**
     * Number columns: G=Booth Size, H=Booth Price, N=Qty, O=Unit Price, P=Item Total,
     * R=Subtotal, U=Discount Amount, W=Tax Amount, X=Total
     */
    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00',
            'H' => '#,##0',
            'N' => '#,##0',
            'O' => '#,##0',
            'P' => '#,##0',
            'R' => '#,##0',
            'U' => '#,##0',
            'W' => '#,##0',
            'X' => '#,##0',
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

        foreach (['G', 'H', 'N', 'O', 'P', 'R', 'U', 'W', 'X'] as $column) {
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
