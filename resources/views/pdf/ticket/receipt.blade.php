@extends('pdf._layout', ['title' => $number])

@php
    $rupiah = fn ($n) => 'Rp'.number_format((float) $n, 0, ',', '.');
    $eventTitle = $order->event?->title ?? 'Event';
@endphp

@section('content')
{{-- ─── Header: Logo + Company Info + RECEIPT ─── --}}
<div class="flex items-start justify-between mb-6 gap-6">
    <div class="flex-1 min-w-0">
        @if (! empty($branding['logo_url']))
            {{-- Fixed height + auto width preserves the logo's native aspect
                 ratio. NEVER add max-width here — the browser would clamp the
                 width while keeping the height fixed, producing a stretched
                 logo. --}}
            <img style="height: 48px; width: auto; display: block;" src="{{ $branding['logo_url'] }}" alt="">
        @else
            <div class="text-[20px] font-semibold tracking-tighter text-black">{{ $branding['company_name'] ?? 'PM One' }}</div>
        @endif
        @if (! empty($branding['address']))
            <div class="text-[11px] text-gray-600 leading-snug mt-1 max-w-[280px]">{{ $branding['address'] }}</div>
        @endif
        @if (! empty($branding['phone']) || ! empty($branding['email']) || ! empty($branding['website']))
            <div class="text-[11px] text-gray-600 leading-snug mt-1">
                @if (! empty($branding['phone'])){{ $branding['phone'] }}@endif
                @if (! empty($branding['phone']) && ! empty($branding['email'])) · @endif
                @if (! empty($branding['email'])){{ $branding['email'] }}@endif
                @if ((! empty($branding['phone']) || ! empty($branding['email'])) && ! empty($branding['website'])) · @endif
                @if (! empty($branding['website'])){{ $branding['website'] }}@endif
            </div>
        @endif
        @if (! empty($branding['tax_id']))
            <div class="text-[11px] text-gray-600 leading-snug mt-1">NPWP: {{ $branding['tax_id'] }}</div>
        @endif
    </div>
    <div class="text-right text-[28px] font-semibold leading-none tracking-tighter text-black shrink-0">RECEIPT</div>
</div>

{{-- ─── Received From + Meta ─── --}}
<table class="w-full mb-4">
    <tr>
        <td class="w-1/2 align-top pr-4">
            <div class="text-[13px] font-semibold text-black tracking-tight mb-2">Received From:</div>
            <div class="text-[13px] text-black pb-1"><strong>{{ $order->buyer_name ?? '-' }}</strong></div>
            @if ($order->buyer_email)<div class="text-[13px] text-black pb-1">{{ $order->buyer_email }}</div>@endif
            @if ($order->buyer_phone)<div class="text-[13px] text-black pb-1">{{ $order->buyer_phone }}</div>@endif
        </td>
        <td class="w-1/2 align-top pl-4">
            <table class="w-full py-1">
                <tr>
                    <td class="text-[13px] font-semibold tracking-tight text-black pr-4 align-middle">Receipt #</td>
                    <td class="text-[13px] text-right text-black align-middle">{{ $number }}</td>
                </tr>
            </table>
            <table class="w-full py-1">
                <tr>
                    <td class="text-[13px] font-semibold tracking-tight text-black pr-4 align-middle">Order #</td>
                    <td class="text-[13px] text-right text-black align-middle">{{ $order->order_number }}</td>
                </tr>
            </table>
            <table class="w-full py-1">
                <tr>
                    <td class="text-[13px] font-semibold tracking-tight text-black pr-4 align-middle">Paid Date</td>
                    <td class="text-[13px] text-right text-black align-middle">{{ $order->paid_at?->format('d M Y, H:i') ?? '-' }}</td>
                </tr>
            </table>
            <table class="w-full py-1">
                <tr>
                    <td class="text-[13px] font-semibold tracking-tight text-black pr-4 align-middle">Status</td>
                    <td class="text-right align-middle">
                        <span class="text-[13px] font-semibold tracking-normal text-green-600 align-middle">PAID</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ─── Items Table ─── --}}
<table class="w-full border-collapse mb-4">
    <thead>
        <tr>
            <th class="w-[34px] py-3 px-2 text-left text-[11px] text-gray-600 font-semibold uppercase tracking-wider border-y border-gray-200">#</th>
            <th class="py-3 px-2 text-left text-[11px] text-gray-600 font-semibold uppercase tracking-wider border-y border-gray-200">Item Description</th>
            <th class="py-3 px-2 text-right text-[11px] text-gray-600 font-semibold uppercase tracking-wider border-y border-gray-200">Unit Price</th>
            <th class="py-3 px-2 text-center text-[11px] text-gray-600 font-semibold uppercase tracking-wider border-y border-gray-200">Qty</th>
            <th class="py-3 px-2 text-right text-[11px] text-gray-600 font-semibold uppercase tracking-wider border-y border-gray-200">Amount</th>
        </tr>
    </thead>
    <tbody>
        @php $rowNum = 0; $allRows = $order->items->count(); @endphp
        @foreach ($order->items as $item)
            @php
                $rowNum++;
                $isLast = $rowNum === $allRows;
                $badges = array_filter([
                    $item->selectedEventDay?->label,
                    $item->ticketSession?->label,
                    $item->phase_label,
                ]);
            @endphp
            <tr>
                <td class="py-3 px-2 align-top text-[12px] text-gray-500 {{ $isLast ? '' : 'border-b border-gray-100' }}">{{ $rowNum }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black {{ $isLast ? '' : 'border-b border-gray-100' }}">
                    <div class="text-[13px] font-semibold text-black tracking-tight leading-tight">{{ $item->ticket?->title ?? 'Ticket' }}</div>
                    <div class="text-[11px] text-gray-500 uppercase tracking-wider mt-2 font-medium leading-none">{{ strtoupper($eventTitle) }}</div>
                    @if (count($badges))
                        <div class="mt-2">
                            @foreach ($badges as $badge)
                                <span class="inline-block py-1.5 px-2.5 border border-gray-200 rounded-md text-[11px] text-gray-600 bg-gray-100 mr-1 font-medium leading-none align-middle">{{ $badge }}</span>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-right {{ $isLast ? '' : 'border-b border-gray-100' }}">{{ $rupiah($item->unit_price) }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-center {{ $isLast ? '' : 'border-b border-gray-100' }}">{{ $item->quantity }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-right {{ $isLast ? '' : 'border-b border-gray-100' }}">{{ $rupiah($item->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ─── Payment Method + Summary ─── --}}
<table class="w-full border-t border-gray-200 pt-4 mt-0">
    <tr>
        <td class="w-[55%] align-top pr-6">
            <div class="text-[13px] font-semibold text-black tracking-tight mb-2">Paid with:</div>
            <table class="mt-1.5 border-collapse">
                <tr>
                    <td class="align-middle p-0">
                        @if ($channelLogo)
                            {{-- Size via a CSS class, not the HTML height attribute:
                                 Tailwind's preflight `img { height: auto }` overrides
                                 the attribute and the logo renders at its full size. --}}
                            <img src="{{ public_path('img/payment-methods/'.$channelLogo) }}" alt="{{ $channelBadge['channel'] ?? '' }}" class="h-12 w-auto">
                        @else
                            <div class="text-[14px] font-semibold text-black tracking-tight leading-tight">{{ $channelBadge['channel'] ?? '-' }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="w-[45%] align-top">
            <table class="w-full">
                <tr>
                    <td class="text-right pr-6 text-gray-600 text-[13px] py-1">Subtotal</td>
                    <td class="text-right text-black text-[13px] py-1 w-[36%]">{{ $rupiah($order->subtotal) }}</td>
                </tr>
                @if ((float) $order->discount_amount > 0)
                    <tr>
                        <td class="text-right pr-6 text-gray-600 text-[13px] py-1">Discount @if ($order->promo_code_applied)({{ $order->promo_code_applied }})@endif</td>
                        <td class="text-right text-black text-[13px] py-1 w-[36%]">-{{ $rupiah($order->discount_amount) }}</td>
                    </tr>
                @endif
                <tr class="border-t border-gray-200">
                    <td class="text-right pr-6 text-black text-[14px] font-semibold tracking-tight uppercase pt-2">Total Paid</td>
                    <td class="text-right text-black text-[14px] font-semibold tracking-tight pt-2 w-[36%]">{{ $rupiah($order->total) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ─── Footer (pushed to page bottom via flex layout) ─── --}}
<div class="mt-auto pt-10 text-center">
    <div class="text-[11px] text-gray-500 font-medium tracking-wider uppercase whitespace-nowrap leading-[20px]">
        Secure checkout powered by
        <img src="{{ public_path('img/payment-methods/'.$paymentProvider['file']) }}" alt="{{ $paymentProvider['name'] }}" class="inline-block h-5 align-middle ml-2 -mt-0.5">
    </div>
</div>
@endsection
