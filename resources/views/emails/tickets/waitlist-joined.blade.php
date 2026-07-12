<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're on the waitlist</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e4e7;">
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <p style="margin:0 0 4px 0; font-size:13px; letter-spacing:0.3px; text-transform:uppercase; color:#71717a;">You're on the waitlist</p>
                            <h1 style="margin:0; font-size:22px; font-weight:600; letter-spacing:-0.4px;">{{ $event?->title ?? 'An event' }}</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 0 32px; font-size:15px; line-height:1.6; color:#3f3f46;">
                            <p style="margin:0 0 16px 0;">
                                @if($ticket)
                                    We'll email you the moment a seat opens up for <strong>{{ $ticket->getTranslation('title', app()->getLocale(), false) ?: $ticket->slug }}</strong>.
                                @else
                                    We'll email you the moment a seat opens up.
                                @endif
                                When it does, you'll get a link to claim it - so don't miss the email.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 32px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #e4e4e7; border-radius:8px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:14px; color:#3f3f46;">
                                        Quantity requested: <strong>{{ $entry->quantity }}</strong>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #f4f4f5; font-size:12px; color:#a1a1aa;">
                            If you no longer want to be notified, you can simply ignore future emails about this waitlist.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
