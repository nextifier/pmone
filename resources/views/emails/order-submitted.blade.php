@component('mail::message')
# New Order Received

A new order has been submitted for **{{ $event->title }}**.

**Order Number:** {{ $order->order_number }}<br>
**Brand:** {{ $brand->name }} ({{ $brand->company_name }})<br>
**Submitted:** {{ $order->submitted_at->format('d M Y, H:i') }}

---

## Order Items

<table class="table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<thead>
<tr>
<th align="left" style="padding:8px;border-bottom:1px solid #edeff2;">Product</th>
<th align="right" style="padding:8px;border-bottom:1px solid #edeff2;">Qty</th>
<th align="right" style="padding:8px;border-bottom:1px solid #edeff2;">Unit Price</th>
<th align="right" style="padding:8px;border-bottom:1px solid #edeff2;">Total</th>
</tr>
</thead>
<tbody>
@foreach($items as $item)
<tr>
<td style="padding:8px;border-bottom:1px solid #edeff2;">{{ $item->product_name }}</td>
<td align="right" style="padding:8px;border-bottom:1px solid #edeff2;">{{ $item->quantity }}</td>
<td align="right" style="padding:8px;border-bottom:1px solid #edeff2;">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
<td align="right" style="padding:8px;border-bottom:1px solid #edeff2;">Rp {{ number_format($item->total_price, 0, ',', '.') }}</td>
</tr>
@endforeach
</tbody>
</table>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tbody>
<tr>
<td>&nbsp;</td>
<td width="260" style="padding:4px 8px;" align="right">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding:4px 0;color:#718096;">Subtotal</td>
<td align="right" style="padding:4px 0;">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td>
</tr>
@if($order->discount_amount && $order->discount_amount > 0)
<tr>
<td style="padding:4px 0;color:#718096;">Discount{{ $order->discount_type === 'percentage' ? ' ('.$order->discount_value.'%)' : '' }}</td>
<td align="right" style="padding:4px 0;">-Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</td>
</tr>
@endif
<tr>
<td style="padding:4px 0;color:#718096;">Tax ({{ $order->tax_rate }}%)</td>
<td align="right" style="padding:4px 0;">Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</td>
</tr>
<tr>
<td style="padding:6px 0 4px;border-top:1px solid #edeff2;font-weight:bold;">Total</td>
<td align="right" style="padding:6px 0 4px;border-top:1px solid #edeff2;font-weight:bold;">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
</tr>
</table>
</td>
</tr>
</tbody>
</table>

@if($order->notes)
**Notes:** {{ $order->notes }}
@endif

---

Thanks,<br>
{{ config('app.name') }}
@endcomponent
