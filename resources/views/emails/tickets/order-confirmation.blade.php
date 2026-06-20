<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Tickets</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <h1 style="font-size:20px;font-weight:600;letter-spacing:-0.02em;margin:0 0 8px;">Your tickets are ready</h1>
        <p style="font-size:14px;line-height:1.6;color:#3f3f46;margin:0 0 16px;">
            Hi {{ $order->buyer_name ?? 'there' }}, your order
            <strong>{{ $order->order_number }}</strong>
            @if($order->event?->title) for <strong>{{ $order->event->title }}</strong> @endif
            is confirmed.
        </p>

        <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:16px;margin:0 0 20px;">
            <p style="font-size:13px;color:#71717a;margin:0 0 4px;">Tickets in this order</p>
            <p style="font-size:15px;font-weight:600;margin:0;">{{ $order->attendees()->count() }} ticket(s)</p>
            @if(! $order->isFree())
                <p style="font-size:13px;color:#71717a;margin:8px 0 0;">Total paid: Rp{{ number_format((float) $order->total, 0, ',', '.') }}</p>
            @endif
        </div>

        <a href="{{ $magicLinkUrl }}" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;font-size:14px;font-weight:500;padding:12px 20px;border-radius:8px;">
            View &amp; manage your tickets
        </a>

        <p style="font-size:12px;line-height:1.6;color:#a1a1aa;margin:24px 0 0;">
            This link lets you view every ticket, personalize attendee names, and share individual tickets. Keep it private.
        </p>
    </div>
</body>
</html>
