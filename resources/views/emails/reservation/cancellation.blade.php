@php
    $project = $reservation->event?->project;
    $signature = $project?->name ?? 'PM One Team';
    $supportEmail = $reservation->hotel?->contact_email ?? $project?->email ?? 'support@pmone.id';

    $methodLabel = $reservation->payment_method?->label();
    $paymentDisplay = $reservation->payment_channel ?: $methodLabel;
@endphp
@component('mail::message')
# Booking Cancelled

Hi {{ $reservation->guest_name }},

Your reservation **{{ $reservation->reservation_number }}** has been cancelled.

## Booking Details

**Hotel:** {{ $reservation->hotel?->name }}

@if ($reservation->event)
**Event:** {{ $reservation->event->title }}
@endif

@foreach ($reservation->items as $item)
- {{ $item->roomType?->name }} - {{ $item->qty }} room(s) - {{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }}
@endforeach

@if ($reservation->paid_at)
## Original Payment

**Amount:** Rp{{ number_format($reservation->total_amount, 0, ',', '.') }}
@if (! empty($paymentDisplay))
**Paid via:** {{ $paymentDisplay }}
@endif
**Paid on:** {{ $reservation->paid_at->format('d M Y, H:i') }}
@endif

@if ($refundAmount > 0)
## Refund

A refund of **Rp{{ number_format($refundAmount, 0, ',', '.') }}** is being processed back to your original payment method. Refunds typically take 3-7 business days to appear.
@else
## No Refund

In accordance with our cancellation policy for bookings cancelled within 7 days of check-in, no refund will be issued.
@endif

@if ($reservation->cancellation_reason)
**Reason:** {{ $reservation->cancellation_reason }}
@endif

@if (! empty($receiptUrl))
## Documents

Your payment receipt is the proof of the amount being refunded - keep it for your records.

@component('mail::button', ['url' => $receiptUrl])
Download Receipt
@endcomponent
@endif

For any questions, please contact:
- Email: {{ $supportEmail }}
@if ($reservation->hotel?->contact_phone)
- Phone: {{ $reservation->hotel->contact_phone }}
@endif

Best regards,
{{ $signature }}
@endcomponent
