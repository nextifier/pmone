@php
    $signature = $event?->project?->name ?? config('app.name');
@endphp
@component('mail::message')
# Payment Receipt

Dear {{ $brand?->name ?? 'Exhibitor' }},

We have received your payment{{ $event ? ' for **'.$event->title.'**' : '' }}. The payment receipt is attached to this email for your records.

**Order Number:** {{ $order->order_number }}<br>
@if($brand?->company_name)
**Company:** {{ $brand->company_name }}<br>
@endif
**Total Paid:** Rp {{ number_format($order->total, 0, ',', '.') }}

Thank you for your payment.

Thanks,<br>
{{ $signature }}
@endcomponent
