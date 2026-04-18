@component('mail::message')
# Booking Cancelled

Hi {{ $reservation->guest_name }},

Your reservation **{{ $reservation->reservation_number }}** has been cancelled.

@if ($refundAmount > 0)
## Refund

A refund of **Rp {{ number_format($refundAmount, 0, ',', '.') }}** is being processed back to your original payment method. Xendit refunds typically take 3-7 business days.
@else
## No Refund

In accordance with our cancellation policy for bookings cancelled within 7 days of check-in, no refund will be issued.
@endif

@if ($reservation->cancellation_reason)
**Reason:** {{ $reservation->cancellation_reason }}
@endif

For any questions, please contact us:
- Email: support@pmone.id

Best regards,
PM One Team
@endcomponent
