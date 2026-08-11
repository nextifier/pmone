<?php

namespace App\Exports;

use App\Enums\AdjustmentKind;
use App\Models\AppliedAdjustment;
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
            ->with(['brandEvent.brand', 'brandEvent.sales', 'items.productCategory', 'adjustments', 'creator'])
            ->withCount('items');
    }

    public function headings(): array
    {
        // Same shape as the Google Sheets orders feed, so the two never disagree:
        // one row per item, the order-level cells repeated and prefixed "Order ",
        // and `Is First Line` to sum them without counting an order once per item.
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
            'Item Discount',
            'Item Penalty',
            'Item Net Total',
            'Item Adjustment Note',
            'Item Notes',
            'Line #',
            'Is First Line',
            'Order Subtotal',
            'Order Discount Amount',
            'Order Penalty Amount',
            'Promo Code',
            'Order Adjustment Reason',
            'Tax Rate (%)',
            'Order Tax Amount',
            'Order Total',
            'Operational Status',
            'Payment Status',
            'Cancellation Reason',
            'Order Notes',
            'Submitted At',
            'Confirmed At',
            'Created By',
            'Currency',
            'Exchange Rate (to IDR)',
            'Order Total (IDR)',
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

        // Live per-item adjustments only. A voided one keeps its `amount` as the
        // audit record of what once applied, so including it would report a
        // discount the order total no longer carries.
        $itemAdjustments = $order->adjustments
            ->filter(fn (AppliedAdjustment $a) => $a->order_item_id !== null && $a->voided_at === null)
            ->groupBy('order_item_id');

        $orderFields = [
            $order->id,
            $order->order_number,
            $brand?->name ?? '-',
            $brand?->company_name ?? '-',
            $brandEvent?->booth_type?->label() ?? '-',
            $brandEvent?->booth_number ?? '-',
            $this->money($brandEvent?->booth_size),
            $this->money($brandEvent?->booth_price),
            $brandEvent?->fascia_name ?? '-',
            $brandEvent?->badge_name ?? '-',
            $brandEvent?->sales?->name ?? '-',
            $order->order_period ? $this->titleCase($order->order_period) : '-',
        ];

        $orderSummary = [
            $this->money($order->subtotal),
            $this->money($order->discount_amount),
            $this->money($order->penalty_amount),
            $order->promo_code_applied ?? '-',
            $this->orderAdjustmentNote($order),
            $this->money($order->tax_rate),
            $this->money($order->tax_amount),
            $this->money($order->total),
            $order->operational_status?->label() ?? '-',
            $order->payment_status?->label() ?? '-',
            $order->cancellation_reason,
            $order->notes,
            $order->submitted_at?->format('Y-m-d H:i:s'),
            $order->confirmed_at?->format('Y-m-d H:i:s'),
            $order->creator?->name ?? '-',
            $order->currency ?? 'IDR',
            $this->money($order->exchange_rate_to_idr),
            $this->money($order->total_idr),
        ];

        if ($items->isEmpty()) {
            return [array_merge(
                $orderFields,
                ['-', '-', 0, 0.0, 0.0, 0.0, 0.0, 0.0, '-', '-', 1, true],
                $orderSummary,
            )];
        }

        $line = 0;

        return $items->map(function ($item) use ($orderFields, $orderSummary, $itemAdjustments, &$line) {
            $line++;

            $adjustments = $itemAdjustments->get($item->id, collect());
            $discount = (float) $adjustments
                ->filter(fn (AppliedAdjustment $a) => $a->kind === AdjustmentKind::Discount)
                ->sum('amount');
            $penalty = (float) $adjustments
                ->filter(fn (AppliedAdjustment $a) => $a->kind === AdjustmentKind::Penalty)
                ->sum('amount');

            return array_merge(
                $orderFields,
                [
                    $item->product_name,
                    $item->productCategory?->title ?? '-',
                    $item->quantity,
                    $this->money($item->unit_price),
                    $this->money($item->total_price),
                    $discount,
                    $penalty,
                    (float) $item->total_price - $discount + $penalty,
                    $adjustments
                        ->map(fn (AppliedAdjustment $a) => $a->rule_snapshot['reason'] ?? $a->label)
                        ->filter()
                        ->implode('; ') ?: '-',
                    $item->notes,
                    $line,
                    $line === 1,
                ],
                $orderSummary,
            );
        })->toArray();
    }

    /**
     * Order-scoped adjustments only; item-scoped ones are reported on their own
     * line so they are not repeated on every row of the order.
     */
    private function orderAdjustmentNote(Order $order): string
    {
        return $order->adjustments
            ->filter(fn (AppliedAdjustment $a) => $a->order_item_id === null)
            ->map(function (AppliedAdjustment $a) {
                $reason = $a->rule_snapshot['reason'] ?? $a->label;

                return $a->voided_at !== null
                    ? "{$reason} (voided: ".($a->void_reason ?: 'no reason').')'
                    : $reason;
            })
            ->filter()
            ->implode('; ') ?: '-';
    }

    /** Money and rates as real numbers; the `decimal:2` casts hand back strings. */
    private function money(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * Numeric columns, keyed to headings(): G=Booth Size, H=Booth Price, O=Qty,
     * P=Unit Price, Q=Item Total, R=Item Discount, S=Item Penalty,
     * T=Item Net Total, W=Line #, Y=Order Subtotal, Z=Order Discount Amount,
     * AA=Order Penalty Amount, AD=Tax Rate, AE=Order Tax Amount,
     * AF=Order Total, AO=Exchange Rate (to IDR), AP=Order Total (IDR).
     */
    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00',
            'H' => '#,##0',
            'O' => '#,##0',
            'P' => '#,##0',
            'Q' => '#,##0',
            'R' => '#,##0',
            'S' => '#,##0',
            'T' => '#,##0',
            'W' => '#,##0',
            'Y' => '#,##0',
            'Z' => '#,##0',
            'AA' => '#,##0',
            'AD' => '#,##0.00',
            'AE' => '#,##0',
            'AF' => '#,##0',
            'AO' => '#,##0.000000',
            'AP' => '#,##0',
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

        foreach (array_keys($this->columnFormats()) as $column) {
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
