@component('mail::message')
# Your Check-in Voucher

Hi {{ $reservation->guest_name }},

The check-in voucher for reservation **{{ $reservation->reservation_number }}** is ready and attached to this email.

## Booking Details

**Hotel:** {{ $reservation->hotel?->name }}
**Address:** {{ $reservation->hotel?->address }}, {{ $reservation->hotel?->city }}

@foreach ($reservation->items as $item)
- {{ $item->roomType?->name }} - {{ $item->qty }} room(s) - {{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }}
@endforeach

## How to Check In

Present the attached voucher at reception when you arrive at the hotel, along with the identification document you used during booking.

## Additional Information

- Check-in time: {{ $reservation->hotel?->check_in_time?->format('H:i') ?? '14:00' }}
- Check-out time: {{ $reservation->hotel?->check_out_time?->format('H:i') ?? '12:00' }}
- Hotel contact: {{ $reservation->hotel?->contact_phone ?? '-' }}

For any questions, please contact us:
- Email: support@pmone.id

Enjoy your stay!

Best regards,
PM One Team
@endcomponent
