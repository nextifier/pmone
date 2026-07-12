<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets are available again</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e4e7;">
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <p style="margin:0 0 4px 0; font-size:13px; letter-spacing:0.3px; text-transform:uppercase; color:#71717a;">Tickets are available again</p>
                            <h1 style="margin:0; font-size:22px; font-weight:600; letter-spacing:-0.4px;">{{ $event?->title ?? 'An event' }}</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 0 32px; font-size:15px; line-height:1.6; color:#3f3f46;">
                            <p style="margin:0 0 16px 0;">
                                @if($ticket)
                                    A seat just freed up for <strong>{{ $ticket->getTranslation('title', app()->getLocale(), false) ?: $ticket->slug }}</strong>.
                                @else
                                    A seat just freed up.
                                @endif
                                This isn't a hold - it's first come, first served, so grab it quickly before someone else does.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:8px 32px 32px 32px;">
                            <a href="{{ $ticketsUrl }}" style="display:inline-block; background-color:#18181b; color:#ffffff; text-decoration:none; font-size:15px; font-weight:500; padding:13px 28px; border-radius:8px;">
                                Buy now
                            </a>
                            <p style="margin:16px 0 0 0; font-size:12px; color:#a1a1aa; word-break:break-all;">
                                {{ $ticketsUrl }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #f4f4f5; font-size:12px; color:#a1a1aa;">
                            You're still on the waitlist and may receive this notification again if more seats free up before you buy.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
