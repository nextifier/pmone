@php
    $signature = $event?->project?->name ?? config('app.name');
@endphp
@component('mail::message')
# Invoice

Dear {{ $brand?->name ?? 'Exhibitor' }},

Please find attached the invoice for your order{{ $event ? ' for **'.$event->title.'**' : '' }}. The attached PDF includes the invoice and tax invoice (faktur pajak).

**Order Number:** {{ $order->order_number }}<br>
@if($brand?->company_name)
**Company:** {{ $brand->company_name }}<br>
@endif
**Total:** {{ $order->formatMoney($order->total) }}

Kindly review the attached document and proceed with the payment according to the instructions provided.

If you have any questions regarding this invoice, please reply to this email.

Thanks,<br>
{{ $signature }}
@endcomponent
