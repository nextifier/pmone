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

@if (! empty($invoiceUrl) || ! empty($receiptUrl))
## Documents

@if (! empty($invoiceUrl))
@component('mail::button', ['url' => $invoiceUrl])
Download Invoice
@endcomponent
@endif

@if (! empty($receiptUrl))
@component('mail::button', ['url' => $receiptUrl, 'color' => 'secondary'])
Download Receipt
@endcomponent
@endif
@endif

For any questions, please contact us:
- Email: support@pmone.id

Best regards,
PM One Team
@endcomponent
