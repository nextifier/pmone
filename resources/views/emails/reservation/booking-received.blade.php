@php
    $isComplimentary = $reservation->payment_method?->value === 'complimentary';
    $project = $reservation->event?->project;
    $signature = $project?->name ?? config('app.name').' Team';

    // For gateway payments (Xendit/Midtrans), the gateway itself isn't a
    // guest-facing label — the channel (BCA, QRIS, …) is what they recognize.
    // For manual bank transfer / complimentary, the enum's own label is
    // meaningful.
    $methodLabel = $reservation->payment_method?->label();
    $paymentDisplay = $reservation->payment_channel ?: $methodLabel;

    $hotelAddress = collect([
        $reservation->hotel?->street,
        $reservation->hotel?->city,
    ])->filter()->implode(', ');
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
@if (! empty($hotelAddress))
**Address:** {{ $hotelAddress }}
@endif

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

@if (! $isComplimentary && (! empty($paymentDisplay) || $reservation->paid_at))
@if (! empty($paymentDisplay))
**Paid via:** {{ $paymentDisplay }}
@endif
@if ($reservation->paid_at)
**Paid on:** {{ $reservation->paid_at->format('d M Y, H:i') }}
@endif
@endif

@component('mail::button', ['url' => $magicLinkUrl])
View Booking Details
@endcomponent

@if (! empty($receiptUrl))
@component('mail::button', ['url' => $receiptUrl, 'color' => 'secondary'])
Download Receipt
@endcomponent
@endif

## Next Steps

Our team will coordinate with the partner hotel. Your check-in voucher will be emailed once the booking is confirmed by the hotel.

@if (! empty($reservation->hotel?->cancellation_policy))
### Cancellation Policy

{{ \Illuminate\Support\Str::limit($reservation->hotel->cancellation_policy, 220) }}
@endif

For any questions, please contact:
- Email: {{ $reservation->hotel?->contact_email ?? config('brand.support_email') }}
- Phone: {{ $reservation->hotel?->contact_phone ?? '-' }}

Best regards,
{{ $signature }}
@endcomponent
