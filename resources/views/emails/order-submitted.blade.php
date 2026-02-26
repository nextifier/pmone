@component('mail::message')
# New Order Received

A new order has been submitted for **{{ $event->title }}**.

**Order Number:** {{ $order->order_number }}
**Brand:** {{ $brand->name }} ({{ $brand->company_name }})
**Submitted:** {{ $order->submitted_at->format('d M Y, H:i') }}

---

## Order Items

@component('mail::table')
| Product | Category | Qty | Unit Price | Total |
|:--------|:---------|:----|:-----------|:------|
@foreach($items as $item)
| {{ $item->product_name }} | {{ $item->product_category }} | {{ $item->quantity }} | Rp {{ number_format($item->unit_price, 0, ',', '.') }} | Rp {{ number_format($item->total_price, 0, ',', '.') }} |
@endforeach
@endcomponent

**Subtotal:** Rp {{ number_format($order->subtotal, 0, ',', '.') }}
@if($order->discount_amount && $order->discount_amount > 0)
**Discount{{ $order->discount_type === 'percentage' ? ' ('.$order->discount_value.'%)' : '' }}:** -Rp {{ number_format($order->discount_amount, 0, ',', '.') }}
@endif
**Tax ({{ $order->tax_rate }}%):** Rp {{ number_format($order->tax_amount, 0, ',', '.') }}
**Total:** Rp {{ number_format($order->total, 0, ',', '.') }}

@if($order->notes)
**Notes:** {{ $order->notes }}
@endif

---

Thanks,<br>
{{ config('app.name') }}
@endcomponent
