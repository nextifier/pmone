@php
    $qrSrc = ($qrPng ?? null) ? $message->embedData($qrPng, 'attendee-qr.png', 'image/png') : ($qrImageUrl ?? null);
@endphp
@if($qrSrc)
    <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:20px;margin:0 0 20px;text-align:center;">
        <img src="{{ $qrSrc }}" alt="Your check-in QR code" width="220" height="220" style="display:block;margin:0 auto;width:220px;height:220px;border:0;outline:none;text-decoration:none;">
        <p style="font-size:13px;color:#71717a;margin:14px 0 0;">{{ $qrCaption ?? 'Show this QR code at the entrance to check in.' }}</p>
    </div>
@endif
