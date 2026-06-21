@php
    $whenLine = ($event && $event->date_label)
        ? 'When: '.$event->date_label.($event->start_time ? ' ('.$event->start_time.($event->end_time ? ' - '.$event->end_time : '').($event->timezone ? ' '.$event->timezone : '').')' : '')
        : null;
    $whereLine = ($event && $event->location) ? 'Where: '.$event->location.($event->hall ? ', '.$event->hall : '') : null;
    $directionsLine = ($event && $event->location_link) ? 'Directions: '.$event->location_link : null;
    $ticketLine = 'Ticket: '.($attendee->ticket?->title ?? 'Admission').($attendee->ticket?->tier ? ' ('.$attendee->ticket->tier.')' : '');
    $orderLine = (($consolidated ?? false) && ($order ?? null))
        ? 'Order: '.$order->order_number.(! $order->isFree() ? ' (Rp'.number_format((float) $order->total, 0, ',', '.').' paid)' : '')
        : null;
    $greetingSuffix = $event?->title ? ' for '.$event->title : '';
@endphp
Here's your ticket

Hi {{ $attendee->name ?? 'there' }}, you've been issued a ticket{{ $greetingSuffix }}.

@if($whenLine){{ $whenLine }}

@endif
@if($whereLine){{ $whereLine }}

@endif
@if($directionsLine){{ $directionsLine }}

@endif
{{ $ticketLine }}
@if(! empty($ticketDayLabel))Valid for: {{ $ticketDayLabel }}
@endif
@if(! empty($ticketSessionLabel))Session: {{ $ticketSessionLabel }}
@endif
@if($orderLine){{ $orderLine }}
@endif

View your e-ticket & QR code: {{ $eticketUrl }}
@if($dashboardUrl ?? null)Manage your tickets (one-tap sign-in): {{ $dashboardUrl }}
@endif

Show the QR code at the entrance to check in. Keep this email private - it is your ticket.
