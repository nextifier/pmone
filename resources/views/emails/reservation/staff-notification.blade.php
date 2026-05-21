@php
    $isCancelled = $eventType === 'cancelled';
    $project = $reservation->event?->project;
@endphp
@component('mail::message')
# Booking {{ $isCancelled ? 'Cancelled' : 'Confirmed' }}

@if ($isCancelled)
A hotel booking has been **cancelled**.
@else
A hotel booking has been **confirmed** and payment received.
@endif

## Booking Details

**Reservation Number:** {{ $reservation->reservation_number }}

**Hotel:** {{ $reservation->hotel?->name ?? '-' }}

@if ($reservation->event)
**Event:** {{ $reservation->event->title }}
@endif

**Guest:** {{ $reservation->guest_name }}

**Email:** {{ $reservation->guest_email }}

@if (! empty($reservation->guest_phone))
**Phone:** {{ $reservation->guest_phone }}
@endif

@if ($reservation->items->isNotEmpty())
### Rooms

@foreach ($reservation->items as $item)
- {{ $item->roomType?->name }} - {{ $item->qty }} room(s) - {{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }} ({{ $item->nights }} night(s))
@endforeach
@endif

@if ($reservation->transfers->isNotEmpty())
### Transfer

@foreach ($reservation->transfers as $transfer)
- {{ $transfer->direction?->label() }} - {{ \Illuminate\Support\Carbon::parse($transfer->transfer_date)->format('d M Y') }} - {{ $transfer->pax_count }} pax
@endforeach
@endif

## {{ $isCancelled ? 'Refund Amount' : 'Amount Paid' }}

**Rp{{ number_format($isCancelled ? (float) ($reservation->refund_amount ?? 0) : (float) $reservation->total_amount, 0, ',', '.') }}**

@if ($isCancelled && ! empty($reservation->cancellation_reason))
**Cancellation Reason:** {{ $reservation->cancellation_reason }}
@endif

@if (! empty($reservationUrl))
@component('mail::button', ['url' => $reservationUrl])
View Reservation
@endcomponent
@endif

@component('mail::subcopy')
Internal notification for {{ $project?->name ?? 'project' }} staff. You are receiving this because your address is configured in Website Settings.
@endcomponent
@endcomponent
