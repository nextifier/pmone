@extends('pdf.reservation._layout', ['title' => $invoiceNumber])

@section('content')
{{-- ─── Header: Logo + Company Info + INVOICE ─── --}}
<div class="flex items-start justify-between mb-6 gap-6">
    <div class="flex-1 min-w-0">
        @if (! empty($branding['logo_url']))
            {{-- Fixed height + auto width preserves the logo's native aspect
                 ratio. NEVER add max-width here — the browser would clamp the
                 width while keeping the height fixed, producing a stretched
                 logo. Wide marks naturally extend further; short marks render
                 compact. --}}
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
    <div class="text-right text-[28px] font-semibold leading-none tracking-tighter text-black shrink-0">INVOICE</div>
</div>

{{-- ─── To: + Meta ─── --}}
<table class="w-full mb-4">
    <tr>
        <td class="w-1/2 align-top pr-4">
            <div class="text-[13px] font-semibold text-black tracking-tight mb-2">To:</div>
            <div class="text-[13px] text-black pb-1"><strong>{{ $r->guest_name }}</strong></div>
            @if ($r->guest_company)<div class="text-[13px] text-black pb-1">{{ $r->guest_company }}</div>@endif
            <div class="text-[13px] text-black pb-1">{{ $r->guest_email }}</div>
            <div class="text-[13px] text-black pb-1">{{ $r->guest_phone }}</div>
        </td>
        <td class="w-1/2 align-top pl-4">
            @foreach ([
                ['Reservation #', $r->reservation_number],
                ['Invoice #', $invoiceNumber],
                ['Invoice Date', $r->created_at?->format('d M Y') ?? '-'],
                ['Due Date', $r->payment_expires_at?->format('d M Y') ?? '-'],
                ['Status', $r->status?->label() ?? '-'],
            ] as [$key, $value])
                <table class="w-full py-1">
                    <tr>
                        <td class="text-[13px] font-semibold tracking-tight text-black pr-4 align-middle">{{ $key }}</td>
                        <td class="text-[13px] text-right text-black align-middle">{{ $value }}</td>
                    </tr>
                </table>
            @endforeach
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
        @php $rowNum = 0; $allRows = $r->items->count() + $r->transfers->count(); @endphp
        @foreach ($r->items as $item)
            @php
                $rowNum++;
                $checkIn = \Illuminate\Support\Carbon::parse($item->check_in_date);
                $checkOut = \Illuminate\Support\Carbon::parse($item->check_out_date);
                $period = $checkIn->format('d') === $checkOut->format('d')
                    ? $checkIn->format('d M Y')
                    : ($checkIn->format('d') . ' - ' . $checkOut->format('d M Y'));
                $totalUnits = $item->nights * $item->qty;
                $isLast = $rowNum === $allRows;
            @endphp
            <tr>
                <td class="py-3 px-2 align-top text-[12px] text-gray-500 {{ $isLast ? '' : 'border-b border-gray-100' }}">{{ $rowNum }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black {{ $isLast ? '' : 'border-b border-gray-100' }}">
                    <div class="text-[13px] font-semibold text-black tracking-tight leading-tight">{{ $item->roomType?->name }} · {{ $r->hotel?->name }}</div>
                    <div class="text-[11px] text-gray-500 uppercase tracking-wider mt-2 font-medium leading-none">{{ strtoupper($period) }}</div>
                    <div class="text-[12px] text-gray-600 mt-2 leading-tight">Hotel accommodation for {{ $item->nights }} {{ $item->nights > 1 ? 'nights' : 'night' }}.</div>
                    <div class="mt-2">
                        <span class="inline-block py-1.5 px-2.5 border border-gray-200 rounded-md text-[11px] text-gray-600 bg-gray-100 mr-1 font-medium leading-none align-middle">{{ $item->qty }} {{ $item->qty > 1 ? 'Rooms' : 'Room' }}</span>
                        <span class="inline-block py-1.5 px-2.5 border border-gray-200 rounded-md text-[11px] text-gray-600 bg-gray-100 mr-1 font-medium leading-none align-middle">{{ $item->nights }} {{ $item->nights > 1 ? 'Nights' : 'Night' }}</span>
                        @if ($r->event)
                            <span class="inline-block py-1.5 px-2.5 border border-gray-200 rounded-md text-[11px] text-gray-600 bg-gray-100 mr-1 font-medium leading-none align-middle">{{ $r->event->title }}</span>
                        @endif
                    </div>
                    @if (! empty($item->notes))
                        <div class="text-[12px] text-gray-600 mt-2 italic leading-tight"><span class="text-gray-500 font-medium">Notes:</span> {{ $item->notes }}</div>
                    @endif
                </td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-right {{ $isLast ? '' : 'border-b border-gray-100' }}">Rp{{ number_format($item->rate_per_night, 0, ',', '.') }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-center {{ $isLast ? '' : 'border-b border-gray-100' }}">{{ $totalUnits }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-right {{ $isLast ? '' : 'border-b border-gray-100' }}">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        @foreach ($r->transfers as $t)
            @php
                $rowNum++;
                $tDate = \Illuminate\Support\Carbon::parse($t->transfer_date);
                $isLast = $rowNum === $allRows;
            @endphp
            <tr>
                <td class="py-3 px-2 align-top text-[12px] text-gray-500 {{ $isLast ? '' : 'border-b border-gray-100' }}">{{ $rowNum }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black {{ $isLast ? '' : 'border-b border-gray-100' }}">
                    <div class="text-[13px] font-semibold text-black tracking-tight leading-tight">{{ $t->direction?->label() }} Transfer · {{ $r->hotel?->name }}</div>
                    <div class="text-[11px] text-gray-500 uppercase tracking-wider mt-2 font-medium leading-none">{{ strtoupper($tDate->format('d M Y')) }}</div>
                    <div class="text-[12px] text-gray-600 mt-2 leading-tight">Airport transfer service.</div>
                    <div class="mt-2">
                        <span class="inline-block py-1.5 px-2.5 border border-gray-200 rounded-md text-[11px] text-gray-600 bg-gray-100 mr-1 font-medium leading-none align-middle">{{ $t->pax_count }} Pax</span>
                        @if ($t->pickup_location)
                            <span class="inline-block py-1.5 px-2.5 border border-gray-200 rounded-md text-[11px] text-gray-600 bg-gray-100 mr-1 font-medium leading-none align-middle">{{ $t->pickup_location }}</span>
                        @endif
                    </div>
                    @if (! empty($t->note))
                        <div class="text-[12px] text-gray-600 mt-2 italic leading-tight"><span class="text-gray-500 font-medium">Notes:</span> {{ $t->note }}</div>
                    @endif
                </td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-right {{ $isLast ? '' : 'border-b border-gray-100' }}">Rp{{ number_format($t->price, 0, ',', '.') }}</td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-center {{ $isLast ? '' : 'border-b border-gray-100' }}">1</td>
                <td class="py-3 px-2 align-top text-[12px] text-black text-right {{ $isLast ? '' : 'border-b border-gray-100' }}">Rp{{ number_format($t->price, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ─── Note + Subtotal/Tax/TOTAL ─── --}}
<table class="w-full border-t border-gray-200 pt-4 mt-0">
    <tr>
        <td class="w-[55%] align-top pr-6">
            @if (! empty($r->special_request))
                <div class="text-[13px] font-semibold text-black tracking-tight mb-2">Special Request:</div>
                <div class="text-[13px] text-black mb-3 italic">{{ $r->special_request }}</div>
            @endif
            @if (! empty($branding['footer_note']))
                <div class="text-[13px] font-semibold text-black tracking-tight mb-2">Note:</div>
                <div class="text-[13px] text-black">{{ $branding['footer_note'] }}</div>
            @endif
        </td>
        <td class="w-[45%] align-top">
            <table class="w-full">
                <tr>
                    <td class="text-right pr-6 text-gray-600 text-[13px] py-1">Subtotal</td>
                    <td class="text-right text-black text-[13px] py-1 w-[36%]">Rp{{ number_format($r->subtotal_rooms + $r->subtotal_transfer, 0, ',', '.') }}</td>
                </tr>
                @if ($r->relationLoaded('adjustments') ? $r->adjustments->whereNull('voided_at')->where('kind', \App\Enums\AdjustmentKind::Penalty)->count() : false)
                    @foreach ($r->adjustments->whereNull('voided_at')->where('kind', \App\Enums\AdjustmentKind::Penalty) as $adj)
                    <tr>
                        <td class="text-right pr-6 text-gray-600 text-[13px] py-1">{{ $adj->label }}</td>
                        <td class="text-right text-black text-[13px] py-1 w-[36%]">+Rp{{ number_format($adj->amount, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                @endif
                @if ($r->relationLoaded('adjustments') ? $r->adjustments->whereNull('voided_at')->where('kind', \App\Enums\AdjustmentKind::Discount)->count() : false)
                    @foreach ($r->adjustments->whereNull('voided_at')->where('kind', \App\Enums\AdjustmentKind::Discount) as $adj)
                    <tr>
                        <td class="text-right pr-6 text-gray-600 text-[13px] py-1">{{ $adj->label }}</td>
                        <td class="text-right text-black text-[13px] py-1 w-[36%]">-Rp{{ number_format($adj->amount, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                @endif
                <tr>
                    <td class="text-right pr-6 text-gray-600 text-[13px] py-1">Tax (PPN {{ (float) ($r->hotel?->tax_percentage ?? 11) }}%)</td>
                    <td class="text-right text-black text-[13px] py-1 w-[36%]">Rp{{ number_format($r->tax_amount, 0, ',', '.') }}</td>
                </tr>
                @if ($r->service_charge_amount > 0)
                <tr>
                    <td class="text-right pr-6 text-gray-600 text-[13px] py-1">Service Charge ({{ (float) ($r->hotel?->service_charge_percentage ?? 0) }}%)</td>
                    <td class="text-right text-black text-[13px] py-1 w-[36%]">Rp{{ number_format($r->service_charge_amount, 0, ',', '.') }}</td>
                </tr>
                @endif
                <tr class="border-t border-gray-200">
                    <td class="text-right pr-6 text-black text-[14px] font-semibold tracking-tight uppercase pt-2">Total</td>
                    <td class="text-right text-black text-[14px] font-semibold tracking-tight pt-2 w-[36%]">Rp{{ number_format($r->total_amount, 0, ',', '.') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ─── Payment CTA (only while the invoice is still awaiting payment) ─── --}}
@if ($r->payment_url && $r->status === \App\Enums\ReservationStatus::PendingPayment)
<div class="mt-4">
    <a href="{{ $r->payment_url }}" class="inline-block bg-black text-white no-underline py-3 px-5 rounded-md text-[13px] font-semibold tracking-tight leading-none align-middle">Click here to pay →</a>
</div>
@endif

{{-- ─── Footer (pushed to page bottom via flex layout) ─── --}}
<div class="mt-auto pt-10 text-center">
    <div class="text-[11px] text-gray-500 font-medium tracking-wider uppercase mb-3 whitespace-nowrap leading-[20px]">
        Secure checkout powered by
        <img src="{{ public_path('images/payment-methods/xendit.svg') }}" alt="Xendit" class="inline-block h-5 align-middle ml-0 -mt-0.5">
    </div>
    @php $rows = array_chunk($enabledPaymentLogos ?? [], 7); @endphp
    @foreach ($rows as $row)
        <div class="flex flex-wrap gap-1 justify-center-safe">
            @foreach ($row as $logo)
                <img src="{{ public_path('images/payment-methods/' . $logo['file']) }}" alt="{{ $logo['alt'] }}" class="h-10 w-auto">
            @endforeach
        </div>
    @endforeach
</div>
@endsection
