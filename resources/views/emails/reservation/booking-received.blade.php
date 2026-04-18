@component('mail::message')
# Thank you, {{ $reservation->guest_name }}!

We have received your payment for reservation **{{ $reservation->reservation_number }}**.

## Booking Details

**Hotel:** {{ $reservation->hotel?->name }}

@if ($reservation->event)
**Event:** {{ $reservation->event->title }}
@endif

@foreach ($reservation->items as $item)
- {{ $item->roomType?->name }} - {{ $item->qty }} room(s) - {{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }} ({{ $item->nights }} night(s))
@endforeach

@if ($reservation->transfers->isNotEmpty())
### Transfer

@foreach ($reservation->transfers as $transfer)
- {{ $transfer->direction?->label() }} - {{ \Illuminate\Support\Carbon::parse($transfer->transfer_date)->format('d M Y') }} - {{ $transfer->pax_count }} pax
@endforeach
@endif

## Total Payment

**Rp {{ number_format($reservation->total_amount, 0, ',', '.') }}**

@component('mail::button', ['url' => $magicLinkUrl])
View Booking Status
@endcomponent

## Next Steps

Our team will coordinate with the partner hotel. Your check-in voucher will be sent via email once confirmed by the hotel (typically within 1-2 business days).

For any questions, please contact us at:
- Email: {{ $reservation->hotel?->contact_email ?? 'support@pmone.id' }}
- Phone: {{ $reservation->hotel?->contact_phone ?? '-' }}

Best regards,
PM One Team
@endcomponent
