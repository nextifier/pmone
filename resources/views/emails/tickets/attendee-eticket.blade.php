<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <h1 style="font-size:20px;font-weight:600;letter-spacing:-0.02em;margin:0 0 8px;">Here's your ticket</h1>
        <p style="font-size:14px;line-height:1.6;color:#3f3f46;margin:0 0 16px;">
            Hi {{ $attendee->name ?? 'there' }}, you've been issued a ticket
            @if($event?->title) for <strong>{{ $event->title }}</strong> @endif.
        </p>

        <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:16px;margin:0 0 20px;">
            <p style="font-size:13px;color:#71717a;margin:0 0 4px;">Ticket</p>
            <p style="font-size:15px;font-weight:600;margin:0;">{{ $attendee->ticket?->title ?? 'Admission' }}</p>
        </div>

        @if($qrImageUrl ?? null)
            <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:20px;margin:0 0 20px;text-align:center;">
                <img src="{{ $qrImageUrl }}" alt="Your check-in QR code" width="220" height="220" style="display:block;margin:0 auto;width:220px;height:220px;border:0;outline:none;text-decoration:none;">
                <p style="font-size:13px;color:#71717a;margin:14px 0 0;">Show this QR code at the entrance to check in.</p>
            </div>
        @endif

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

        <p style="font-size:12px;line-height:1.6;color:#a1a1aa;margin:24px 0 0;">
            If the QR code above does not appear, tap "show images" in your email app, or open your e-ticket using the button. Keep this email private - it is your ticket.
        </p>
    </div>
</body>
</html>
