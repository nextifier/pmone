@php
    $isComplimentary = $reservation->payment_method?->value === 'complimentary';
@endphp
@component('mail::message')
# Thank you, {{ $reservation->guest_name }}!

@if ($isComplimentary)
Your complimentary reservation **{{ $reservation->reservation_number }}** has been confirmed. No payment is required.
@else
We have received your payment for reservation **{{ $reservation->reservation_number }}**. Your booking is now confirmed.
@endif

## Booking Details

**Hotel:** {{ $reservation->hotel?->name }}

@if ($reservation->event)
**Event:** {{ $reservation->event->title }}
@endif

@foreach ($reservation->items as $item)
- {{ $item->roomType?->name }} - {{ $item->qty }} room(s) - {{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }} ({{ $item->nights }} night(s))
@if (! empty($item->notes))
   Notes: {{ $item->notes }}
@endif
@endforeach

@if ($reservation->transfers->isNotEmpty())
### Transfer

@foreach ($reservation->transfers as $transfer)
- {{ $transfer->direction?->label() }} - {{ \Illuminate\Support\Carbon::parse($transfer->transfer_date)->format('d M Y') }} - {{ $transfer->pax_count }} pax
@if (! empty($transfer->note))
   Notes: {{ $transfer->note }}
@endif
@endforeach
@endif

@if (! empty($reservation->special_request))
### Special Request

> {{ $reservation->special_request }}
@endif

## {{ $isComplimentary ? 'Total' : 'Amount Paid' }}

**Rp{{ number_format($reservation->total_amount, 0, ',', '.') }}**

@component('mail::button', ['url' => $magicLinkUrl])
View Booking Status
@endcomponent

@if (! empty($receiptUrl))
@component('mail::button', ['url' => $receiptUrl, 'color' => 'secondary'])
Download Receipt
@endcomponent
@endif

@if (! empty($invoiceUrl))
@component('mail::button', ['url' => $invoiceUrl, 'color' => 'secondary'])
Download Invoice
@endcomponent
@endif

## Next Steps

Our team will coordinate with the partner hotel. Your check-in voucher will be emailed once the booking is confirmed by the hotel.

For any questions, please contact us at:
- Email: {{ $reservation->hotel?->contact_email ?? 'support@pmone.id' }}
- Phone: {{ $reservation->hotel?->contact_phone ?? '-' }}

Best regards,
PM One Team
@endcomponent
