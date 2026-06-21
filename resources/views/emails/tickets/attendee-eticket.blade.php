@extends('emails.tickets._layout', ['title' => 'Your ticket'])

@section('content')
    <h1 style="font-size:20px;font-weight:600;letter-spacing:-0.02em;margin:0 0 8px;">Here's your ticket</h1>
    <p style="font-size:14px;line-height:1.6;color:#3f3f46;margin:0 0 20px;">
        Hi {{ $attendee->name ?? 'there' }}, you've been issued a ticket
        @if($event?->title) for <strong>{{ $event->title }}</strong> @endif.
    </p>

    @include('emails.tickets._partials.event-when-where')

    @php
        $qrSrc = ($qrPng ?? null) ? $message->embedData($qrPng, 'attendee-qr.png', 'image/png') : ($qrImageUrl ?? null);
    @endphp
    @if($qrSrc)
        <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:20px;margin:0 0 20px;text-align:center;">
            <img src="{{ $qrSrc }}" alt="Your check-in QR code" width="220" height="220" style="display:block;margin:0 auto;width:220px;height:220px;border:0;outline:none;text-decoration:none;">
            <p style="font-size:13px;color:#71717a;margin:14px 0 0;">Show this QR code at the entrance to check in.</p>
        </div>
    @endif

    <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:16px;margin:0 0 20px;">
        <p style="font-size:13px;color:#71717a;margin:0 0 2px;">Ticket</p>
        <p style="font-size:15px;font-weight:600;margin:0;">
            {{ $attendee->ticket?->title ?? 'Admission' }}@if($attendee->ticket?->tier) <span style="color:#71717a;font-weight:400;">&middot; {{ $attendee->ticket->tier }}</span>@endif
        </p>

        @if(! empty($ticketDayLabel))
            <p style="font-size:13px;color:#71717a;margin:12px 0 2px;">Valid for</p>
            <p style="font-size:14px;margin:0;">{{ $ticketDayLabel }}</p>
        @endif

        @if(! empty($ticketSessionLabel))
            <p style="font-size:13px;color:#71717a;margin:12px 0 2px;">Session</p>
            <p style="font-size:14px;margin:0;">{{ $ticketSessionLabel }}</p>
        @endif

        @if(! empty($attendee->ticket?->benefits))
            <p style="font-size:13px;color:#71717a;margin:12px 0 6px;">Includes</p>
            @foreach($attendee->ticket->benefits as $benefit)
                <p style="font-size:14px;color:#18181b;margin:0 0 4px;line-height:1.4;">&checkmark; {{ $benefit }}</p>
            @endforeach
        @endif

        @if(($consolidated ?? false) && ($order ?? null))
            <p style="font-size:12px;color:#a1a1aa;margin:14px 0 0;border-top:1px solid #f4f4f5;padding-top:12px;">
                Order {{ $order->order_number }}@if(! $order->isFree()) &middot; Rp{{ number_format((float) $order->total, 0, ',', '.') }} paid @endif
            </p>
        @endif
    </div>

    <a href="{{ $eticketUrl }}" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;font-size:14px;font-weight:500;padding:12px 20px;border-radius:8px;">
        View your e-ticket &amp; QR code
    </a>

    @if($dashboardUrl ?? null)
        <br>
        <a href="{{ $dashboardUrl }}" style="display:inline-block;margin-top:10px;background:#ffffff;color:#18181b;text-decoration:none;font-size:14px;font-weight:500;padding:12px 20px;border-radius:8px;border:1px solid #e4e4e7;">
            Go to dashboard
        </a>
        <p style="font-size:12px;line-height:1.6;color:#a1a1aa;margin:8px 0 0;">
            One-tap sign-in to manage your tickets - no password needed.
        </p>
    @endif

    <p style="font-size:12px;line-height:1.6;color:#a1a1aa;margin:20px 0 0;">
        If the QR code above does not appear, tap "show images" in your email app, or open your e-ticket using the button.
    </p>
@endsection
