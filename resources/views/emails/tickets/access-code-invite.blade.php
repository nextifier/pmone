<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your invitation</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e4e7;">
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <p style="margin:0 0 4px 0; font-size:13px; letter-spacing:0.3px; text-transform:uppercase; color:#71717a;">You're invited</p>
                            <h1 style="margin:0; font-size:22px; font-weight:600; letter-spacing:-0.4px;">{{ $event?->title ?? 'An event' }}</h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 0 32px; font-size:15px; line-height:1.6; color:#3f3f46;">
                            <p style="margin:0 0 16px 0;">
                                You've been given an access code to unlock a special ticket. Click the button below — your code is filled in automatically and the ticket will be ready at checkout.
                            </p>
                        </td>
                    </tr>

                    @if($unlocks->isNotEmpty())
                        <tr>
                            <td style="padding:0 32px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px solid #e4e4e7; border-radius:8px;">
                                    <tr>
                                        <td style="padding:14px 16px;">
                                            <p style="margin:0 0 6px 0; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; color:#71717a;">Unlocks</p>
                                            @foreach($unlocks as $ticket)
                                                <p style="margin:0; font-size:14px; font-weight:500;">{{ $ticket->getTranslation('title', app()->getLocale(), false) ?: $ticket->slug }}</p>
                                            @endforeach
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:16px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fafafa; border:1px dashed #d4d4d8; border-radius:8px;">
                                <tr>
                                    <td align="center" style="padding:16px;">
                                        <p style="margin:0 0 4px 0; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; color:#71717a;">Your access code</p>
                                        <p style="margin:0; font-size:22px; font-weight:600; letter-spacing:2px;">{{ $code->code }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:8px 32px 32px 32px;">
                            <a href="{{ $inviteUrl }}" style="display:inline-block; background-color:#18181b; color:#ffffff; text-decoration:none; font-size:15px; font-weight:500; padding:13px 28px; border-radius:8px;">
                                Claim your ticket
                            </a>
                            <p style="margin:16px 0 0 0; font-size:12px; color:#a1a1aa; word-break:break-all;">
                                {{ $inviteUrl }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px; border-top:1px solid #f4f4f5; font-size:12px; color:#a1a1aa;">
                            If you weren't expecting this invitation, you can safely ignore this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
