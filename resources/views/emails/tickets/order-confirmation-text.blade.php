@php
    $whenLine = ($event && $event->date_label)
        ? 'When: '.$event->date_label.($event->start_time ? ' ('.$event->start_time.($event->end_time ? ' - '.$event->end_time : '').($event->timezone ? ' '.$event->timezone : '').')' : '')
        : null;
    $whereLine = ($event && $event->location) ? 'Where: '.$event->location.($event->hall ? ', '.$event->hall : '') : null;
    $directionsLine = ($event && $event->location_link) ? 'Directions: '.$event->location_link : null;
    $totalLine = ($order->isFree() ? 'Total' : 'Total paid').': '.($order->isFree() ? 'Free' : 'Rp'.number_format((float) $order->total, 0, ',', '.'));
    $receiptLine = (! $order->isFree() && ! empty($receiptUrl)) ? 'Download receipt: '.$receiptUrl : null;
    $invoiceLine = (! $order->isFree() && ! empty($invoiceUrl)) ? 'Download invoice: '.$invoiceUrl : null;
@endphp
Your tickets are ready

Hi {{ $order->buyer_name ?? 'there' }}, your order {{ $order->order_number }}@if($order->event?->title) for {{ $order->event->title }}@endif is confirmed.

@if($whenLine){{ $whenLine }}

@endif
@if($whereLine){{ $whereLine }}

@endif
@if($directionsLine){{ $directionsLine }}

@endif
Order summary:
@foreach($order->items as $item)
- {{ $item->quantity }}x {{ $item->ticket?->title ?? 'Ticket' }}@if($item->phase_label) ({{ $item->phase_label }})@endif: Rp{{ number_format((float) $item->subtotal, 0, ',', '.') }}
@endforeach
@if((float) $order->discount_amount > 0)Subtotal: Rp{{ number_format((float) $order->subtotal, 0, ',', '.') }}
Discount: -Rp{{ number_format((float) $order->discount_amount, 0, ',', '.') }}
@endif
{{ $totalLine }}

View & manage your tickets: {{ $magicLinkUrl }}
@if($receiptLine){{ $receiptLine }}
@endif
@if($invoiceLine){{ $invoiceLine }}
@endif

This link lets you view every ticket, personalize attendee names, and share individual tickets. Keep it private.
