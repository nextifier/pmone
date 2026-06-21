@extends('emails.tickets._layout', ['title' => 'Your tickets'])

@section('content')
    <h1 style="font-size:20px;font-weight:600;letter-spacing:-0.02em;margin:0 0 8px;">Your tickets are ready</h1>
    <p style="font-size:14px;line-height:1.6;color:#3f3f46;margin:0 0 20px;">
        Hi {{ $order->buyer_name ?? 'there' }}, your order
        <strong>{{ $order->order_number }}</strong>
        @if($order->event?->title) for <strong>{{ $order->event->title }}</strong> @endif
        is confirmed.
    </p>

    @include('emails.tickets._partials.event-when-where')

    <div style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:16px;margin:0 0 20px;">
        <p style="font-size:13px;color:#71717a;margin:0 0 10px;">Order summary</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            @foreach($order->items as $item)
                <tr>
                    <td style="font-size:14px;color:#18181b;padding:0 0 6px;line-height:1.4;">
                        {{ $item->quantity }}&times; {{ $item->ticket?->title ?? 'Ticket' }}@if($item->phase_label) <span style="color:#71717a;">&middot; {{ $item->phase_label }}</span>@endif
                    </td>
                    <td align="right" valign="top" style="font-size:14px;color:#18181b;padding:0 0 6px;white-space:nowrap;">
                        Rp{{ number_format((float) $item->subtotal, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-top:1px solid #e4e4e7;margin-top:10px;">
            @if((float) $order->discount_amount > 0)
                <tr>
                    <td style="font-size:13px;color:#71717a;padding:10px 0 0;">Subtotal</td>
                    <td align="right" style="font-size:13px;color:#71717a;padding:10px 0 0;">Rp{{ number_format((float) $order->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td style="font-size:13px;color:#71717a;padding:4px 0 0;">Discount</td>
                    <td align="right" style="font-size:13px;color:#71717a;padding:4px 0 0;">-Rp{{ number_format((float) $order->discount_amount, 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr>
                <td style="font-size:15px;font-weight:600;padding:{{ (float) $order->discount_amount > 0 ? '6' : '10' }}px 0 0;">{{ $order->isFree() ? 'Total' : 'Total paid' }}</td>
                <td align="right" style="font-size:15px;font-weight:600;padding:{{ (float) $order->discount_amount > 0 ? '6' : '10' }}px 0 0;">@if($order->isFree()) Free @else Rp{{ number_format((float) $order->total, 0, ',', '.') }} @endif</td>
            </tr>
        </table>
    </div>

    <a href="{{ $magicLinkUrl }}" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;font-size:14px;font-weight:500;padding:12px 20px;border-radius:8px;">
        View &amp; manage your tickets
    </a>

    @if(! $order->isFree() && (! empty($receiptUrl) || ! empty($invoiceUrl)))
        <p style="font-size:13px;line-height:1.6;color:#3f3f46;margin:16px 0 0;">
            @if(! empty($receiptUrl))<a href="{{ $receiptUrl }}" style="color:#18181b;text-decoration:underline;">Download receipt</a>@endif
            @if(! empty($receiptUrl) && ! empty($invoiceUrl)) &middot; @endif
            @if(! empty($invoiceUrl))<a href="{{ $invoiceUrl }}" style="color:#18181b;text-decoration:underline;">Download invoice</a>@endif
        </p>
    @endif

    <p style="font-size:12px;line-height:1.6;color:#a1a1aa;margin:20px 0 0;">
        This link lets you view every ticket, personalize attendee names, and share individual tickets.
    </p>
@endsection
