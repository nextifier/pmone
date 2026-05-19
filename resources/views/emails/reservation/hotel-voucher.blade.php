@component('mail::message')
# Your Check-in Voucher

Hi {{ $reservation->guest_name }},

The check-in voucher for reservation **{{ $reservation->reservation_number }}** is ready and attached to this email.

## Booking Details

**Hotel:** {{ $reservation->hotel?->name }}
**Address:** {{ $reservation->hotel?->address }}, {{ $reservation->hotel?->city }}

@foreach ($reservation->items as $item)
- {{ $item->roomType?->name }} - {{ $item->qty }} room(s) - {{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }}
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

## How to Check In

Present the attached voucher at reception when you arrive at the hotel, along with the identification document you used during booking.

## Additional Information

- Check-in time: 14:00
- Check-out time: 12:00
- Hotel contact: {{ $reservation->hotel?->contact_phone ?? '-' }}

@if (! empty($invoiceUrl) || ! empty($receiptUrl))
## Documents

@if (! empty($receiptUrl))
@component('mail::button', ['url' => $receiptUrl])
Download Receipt
@endcomponent
@endif

@if (! empty($invoiceUrl))
@component('mail::button', ['url' => $invoiceUrl, 'color' => 'secondary'])
Download Invoice
@endcomponent
@endif
@endif

For any questions, please contact us:
- Email: support@pmone.id

Enjoy your stay!

Best regards,
PM One Team
@endcomponent
