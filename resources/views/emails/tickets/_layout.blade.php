<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Your ticket')</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        @if(! empty($brandLogoUrl))
            <div style="margin:0 0 24px;">
                <img src="{{ $brandLogoUrl }}" alt="{{ $event?->project?->name ?? 'Logo' }}" height="40" style="display:block;height:40px;width:auto;border:0;outline:none;text-decoration:none;">
            </div>
        @elseif($event?->project?->name)
            <div style="margin:0 0 24px;">
                <span style="font-size:16px;font-weight:600;letter-spacing:-0.01em;">{{ $event->project->name }}</span>
            </div>
        @endif

        @yield('content')

        <div style="border-top:1px solid #e4e4e7;margin:32px 0 0;padding:16px 0 0;">
            @if($event?->project?->email)
                <p style="font-size:12px;line-height:1.6;color:#a1a1aa;margin:0 0 4px;">Need help? Contact {{ $event->project->email }}</p>
            @endif
            <p style="font-size:12px;line-height:1.6;color:#a1a1aa;margin:0;">Keep this email private - it is your ticket.</p>
        </div>
    </div>
</body>
</html>
