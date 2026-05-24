@php
    $project = $reservation->event?->project;
    $signature = $project?->name ?? 'PM One Team';
    $supportEmail = $reservation->hotel?->contact_email ?? $project?->email ?? 'support@pmone.id';
    $hotelAddress = collect([
        $reservation->hotel?->street,
        $reservation->hotel?->city,
    ])->filter()->implode(', ');
@endphp
@component('mail::message')
# Your Check-in Voucher

Hi {{ $reservation->guest_name }},

The check-in voucher for reservation **{{ $reservation->reservation_number }}** is ready. It is attached to this email, and you can also download it using the button below.

## Booking Details

**Reservation Number:** {{ $reservation->reservation_number }}

**Hotel:** {{ $reservation->hotel?->name }}
@if (! empty($hotelAddress))
**Address:** {{ $hotelAddress }}
@endif

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

## Total Paid

**Rp{{ number_format($reservation->total_amount, 0, ',', '.') }}**

## How to Check In

Present the attached voucher at reception when you arrive at the hotel, along with the identification document you used during booking.

## Additional Information

- Check-in time: 14:00
- Check-out time: 12:00
- Hotel contact: {{ $reservation->hotel?->contact_phone ?? '-' }}

@if (! empty($voucherUrl) || ! empty($receiptUrl))
## Documents

@if (! empty($voucherUrl))
@component('mail::button', ['url' => $voucherUrl])
Download Hotel Voucher
@endcomponent
@endif

@if (! empty($receiptUrl))
@component('mail::button', ['url' => $receiptUrl, 'color' => 'secondary'])
Download Receipt
@endcomponent
@endif
@endif

@if (! empty($reservation->hotel?->cancellation_policy))
### Cancellation Policy

{{ \Illuminate\Support\Str::limit($reservation->hotel->cancellation_policy, 220) }}
@endif

For any questions, please contact:
- Email: {{ $supportEmail }}
@if ($reservation->hotel?->contact_phone)
- Phone: {{ $reservation->hotel->contact_phone }}
@endif

Enjoy your stay!

Best regards,
{{ $signature }}
@endcomponent
