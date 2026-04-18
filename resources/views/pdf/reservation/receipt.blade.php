@extends('pdf.reservation._layout', ['title' => $receiptNumber])

@section('content')
<div class="header">
    <div class="header-left">
        <div class="brand-name">{{ $branding['company_name'] ?? 'PM One' }}</div>
        @if (!empty($branding['address']))
            <div style="margin-top: 4px;">{{ $branding['address'] }}</div>
        @endif
        @if (!empty($branding['city']) || !empty($branding['country']))
            <div>{{ collect([$branding['city'] ?? null, $branding['country'] ?? null])->filter()->join(', ') }}</div>
        @endif
        @if (!empty($branding['email']))
            <div>Email: {{ $branding['email'] }}</div>
        @endif
    </div>
    <div class="header-right">
        <div class="doc-title">RECEIPT</div>
        <div class="doc-meta">
            <strong>{{ $receiptNumber }}</strong><br>
            Paid: {{ $r->paid_at?->format('d M Y H:i') }}<br>
            Method: {{ $r->payment_method?->label() ?? '-' }}
        </div>
    </div>
</div>

<div class="stamp">PAID</div>

<div class="info-grid" style="clear: both;">
    <div class="info-cell">
        <div class="section-title">Received From</div>
        <div><strong>{{ $r->guest_name }}</strong></div>
        <div>{{ $r->guest_email }}</div>
        <div>{{ $r->guest_phone }}</div>
    </div>
    <div class="info-cell">
        <div class="section-title">Reservation</div>
        <div><strong>{{ $r->reservation_number }}</strong></div>
        <div>{{ $r->hotel?->name }}</div>
        @if ($r->event)<div>Event: {{ $r->event->title }}</div>@endif
    </div>
</div>

<div class="section-title">Items</div>
<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($r->items as $item)
        <tr>
            <td>{{ $item->roomType?->name }} ({{ $item->nights }}n, {{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M') }} - {{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }})</td>
            <td class="text-right">{{ $item->qty }}</td>
            <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    @foreach ($r->transfers as $t)
        <tr>
            <td>Transfer {{ $t->direction?->label() }} - {{ \Illuminate\Support\Carbon::parse($t->transfer_date)->format('d M Y') }}</td>
            <td class="text-right">{{ $t->pax_count }} pax</td>
            <td class="text-right">Rp {{ number_format($t->price, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="totals">
    <div class="row"><div class="label">Subtotal</div><div class="value">Rp {{ number_format($r->subtotal_rooms + $r->subtotal_transfer, 0, ',', '.') }}</div></div>
    <div class="row"><div class="label">Tax</div><div class="value">Rp {{ number_format($r->tax_amount, 0, ',', '.') }}</div></div>
    @if ($r->service_charge_amount > 0)
    <div class="row"><div class="label">Service</div><div class="value">Rp {{ number_format($r->service_charge_amount, 0, ',', '.') }}</div></div>
    @endif
    <div class="row grand"><div class="label">TOTAL PAID</div><div class="value">Rp {{ number_format($r->total_amount, 0, ',', '.') }}</div></div>
</div>

@if (!empty($branding['footer_note']))
<div class="footer-note">{{ $branding['footer_note'] }}</div>
@endif
@endsection
