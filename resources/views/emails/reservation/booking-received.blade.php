@php
    $isPaid = in_array($reservation->status?->value, ['paid', 'voucher_sent'], true);
    $isComplimentary = $reservation->payment_method?->value === 'complimentary';
@endphp
@component('mail::message')
# Thank you, {{ $reservation->guest_name }}!

@if ($isComplimentary)
Your complimentary reservation **{{ $reservation->reservation_number }}** has been confirmed. No payment is required.
@elseif ($isPaid)
We have received your payment for reservation **{{ $reservation->reservation_number }}**.
@else
We have received your booking **{{ $reservation->reservation_number }}**. Please complete the payment to confirm your reservation.
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

## Total {{ $isPaid ? 'Paid' : 'Due' }}

**Rp{{ number_format($reservation->total_amount, 0, ',', '.') }}**

@if (! $isPaid && ! $isComplimentary && ! empty($reservation->payment_url))
@component('mail::button', ['url' => $reservation->payment_url])
Pay Now
@endcomponent
@endif

@component('mail::button', ['url' => $magicLinkUrl, 'color' => $isPaid ? 'primary' : 'secondary'])
View Booking Status
@endcomponent

@if (! empty($invoiceUrl))
@component('mail::button', ['url' => $invoiceUrl, 'color' => 'secondary'])
Download Invoice
@endcomponent
@endif

## Next Steps

@if ($isPaid || $isComplimentary)
Our team will coordinate with the partner hotel. Your check-in voucher will be emailed once confirmed by the hotel.
@else
Complete the payment via the link above. Once confirmed by our payment provider, we'll coordinate with the hotel and email your check-in voucher.
@endif

For any questions, please contact us at:
- Email: {{ $reservation->hotel?->contact_email ?? 'support@pmone.id' }}
- Phone: {{ $reservation->hotel?->contact_phone ?? '-' }}

Best regards,
PM One Team
@endcomponent
