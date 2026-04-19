@extends('pdf.reservation._layout', ['title' => $invoiceNumber])

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
        @if (!empty($branding['phone']))
            <div>Phone: {{ $branding['phone'] }}</div>
        @endif
        @if (!empty($branding['email']))
            <div>Email: {{ $branding['email'] }}</div>
        @endif
    </div>
    <div class="header-right">
        <div class="doc-title">INVOICE</div>
        <div class="doc-meta">
            <strong>{{ $invoiceNumber }}</strong><br>
            Issued: {{ $r->created_at?->format('d M Y') }}<br>
            Due: {{ $r->payment_expires_at?->format('d M Y') }}
        </div>
    </div>
</div>

<div class="info-grid">
    <div class="info-cell">
        <div class="section-title">Bill To</div>
        <div><strong>{{ $r->guest_name }}</strong></div>
        @if ($r->guest_company)<div>{{ $r->guest_company }}</div>@endif
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

<div class="section-title">Rooms</div>
<table class="items">
    <thead>
        <tr>
            <th>Room</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th class="text-right">Nights</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Rate</th>
            <th class="text-right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($r->items as $item)
        <tr>
            <td>{{ $item->roomType?->name }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($item->check_in_date)->format('d M Y') }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($item->check_out_date)->format('d M Y') }}</td>
            <td class="text-right">{{ $item->nights }}</td>
            <td class="text-right">{{ $item->qty }}</td>
            <td class="text-right">Rp {{ number_format($item->rate_per_night, 0, ',', '.') }}</td>
            <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@if ($r->transfers->isNotEmpty())
<div class="section-title">Transfer</div>
<table class="items">
    <thead>
        <tr>
            <th>Direction</th>
            <th>Date</th>
            <th class="text-right">Pax</th>
            <th class="text-right">Price</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($r->transfers as $t)
        <tr>
            <td>{{ $t->direction?->label() }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($t->transfer_date)->format('d M Y') }}</td>
            <td class="text-right">{{ $t->pax_count }}</td>
            <td class="text-right">Rp {{ number_format($t->price, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<div class="totals">
    <div class="row"><div class="label">Subtotal Rooms</div><div class="value">Rp {{ number_format($r->subtotal_rooms, 0, ',', '.') }}</div></div>
    <div class="row"><div class="label">Subtotal Transfer</div><div class="value">Rp {{ number_format($r->subtotal_transfer, 0, ',', '.') }}</div></div>
    @if ($r->surcharge_amount > 0)
    <div class="row"><div class="label">Surcharge</div><div class="value">Rp {{ number_format($r->surcharge_amount, 0, ',', '.') }}</div></div>
    @endif
    <div class="row"><div class="label">Tax (PPN)</div><div class="value">Rp {{ number_format($r->tax_amount, 0, ',', '.') }}</div></div>
    @if ($r->service_charge_amount > 0)
    <div class="row"><div class="label">Service Charge</div><div class="value">Rp {{ number_format($r->service_charge_amount, 0, ',', '.') }}</div></div>
    @endif
    <div class="row grand"><div class="label">TOTAL</div><div class="value">Rp {{ number_format($r->total_amount, 0, ',', '.') }}</div></div>
</div>

@if (!empty($branding['bank_accounts']))
<div style="clear: both; margin-top: 30px;">
    <div class="section-title">Bank Transfer</div>
    @foreach ($branding['bank_accounts'] as $bank)
        <div>{{ $bank['bank_name'] ?? '' }} - {{ $bank['account_number'] ?? '' }} a/n {{ $bank['account_name'] ?? '' }}</div>
    @endforeach
</div>
@endif

@if (!empty($branding['footer_note']))
<div class="footer-note">{{ $branding['footer_note'] }}</div>
@endif
@endsection
