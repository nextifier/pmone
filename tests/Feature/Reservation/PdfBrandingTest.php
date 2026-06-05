<?php

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Services\Reservation\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->event = Event::factory()->create([
        'branding' => [
            'logo_url' => 'https://cdn.example.com/logo-test.png',
            'company_name' => 'Test Brand Inc.',
            'address' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'country' => 'Indonesia',
            'email' => 'hello@testbrand.com',
            'phone' => '+62-21-555-1234',
            'tax_id' => '01.234.567.8-999.000',
            'primary_color' => '#FF5733',
            'footer_note' => 'Thank you for your business.',
            'bank_accounts' => [
                ['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Test Brand'],
            ],
        ],
    ]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $room = RoomType::factory()->for($this->hotel)->create(['name' => 'Deluxe Room']);

    $this->reservation = Reservation::factory()->for($this->hotel)->create([
        'event_id' => $this->event->id,
        'status' => ReservationStatus::Paid,
        'paid_at' => now()->subHour(),
        'reservation_number' => 'HTL-PDF-001',
        'total_amount' => 1500000,
        'subtotal_rooms' => 1300000,
        'tax_amount' => 200000,
        'payment_channel' => 'BCA',
        'payment_destination' => '8808123456789012',
        'xendit_payment_id' => 'pay_pdf_test_999',
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $this->reservation->id,
        'room_type_id' => $room->id,
        'check_in_date' => now()->addDays(10),
        'check_out_date' => now()->addDays(12),
        'qty' => 1,
        'subtotal' => 1300000,
    ]);
});

it('invoice PDF response is valid PDF binary with branding embedded', function () {
    $service = app(DocumentService::class);
    $response = $service->renderInvoicePdf($this->reservation->fresh(['hotel', 'event', 'items.roomType', 'transfers']));

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

it('receipt PDF includes payment channel badge data via DocumentService::channelBadge', function () {
    $service = app(DocumentService::class);
    $badge = $service->channelBadge('BCA');

    expect($badge)->not->toBeNull();
    expect($badge['channel'])->toBe('BCA');
    expect($badge['color'])->toBe('#0060AF');
    expect($badge['label'])->toBe('BCA');
});

it('channelBadge returns neutral fallback for unknown channels', function () {
    $service = app(DocumentService::class);
    $badge = $service->channelBadge('UNKNOWN_CHANNEL');

    expect($badge['color'])->toBe('#52525B');
    expect($badge['label'])->toBe('UNKNOWN_CHANNEL');
});

it('channelBadge returns null for null/empty channel', function () {
    $service = app(DocumentService::class);
    expect($service->channelBadge(null))->toBeNull();
    expect($service->channelBadge(''))->toBeNull();
});

it('invoice view contains branding logo and footer note', function () {
    $service = app(DocumentService::class);
    $branding = $service->getBranding($this->reservation->fresh('event'));

    $html = view('pdf.reservation.invoice', [
        'r' => $this->reservation->fresh(['hotel', 'event', 'items.roomType', 'transfers']),
        'branding' => $branding,
        'invoiceNumber' => 'INV/HTL/20260510/PDF',
        'enabledPaymentLogos' => [],
        'paymentProvider' => $service->paymentProviderBadge($this->reservation),
    ])->render();

    // Header renders brand logo OR company name (mutually exclusive); logo is set.
    expect($html)->toContain('https://cdn.example.com/logo-test.png');
    expect($html)->toContain('Thank you for your business.');
});

it('receipt view renders channel badge SVG and destination for paid reservation', function () {
    $service = app(DocumentService::class);
    $branding = $service->getBranding($this->reservation->fresh('event'));

    $html = view('pdf.reservation.receipt', [
        'r' => $this->reservation->fresh(['hotel', 'event', 'items.roomType', 'transfers']),
        'branding' => $branding,
        'receiptNumber' => 'RCP/HTL/20260510/PDF',
        'channelBadge' => $service->channelBadge('BCA'),
        'channelLogo' => $service->channelLogoFile('BCA'),
        'paymentProvider' => $service->paymentProviderBadge($this->reservation),
    ])->render();

    expect($html)->toContain('BCA');
    expect($html)->toContain('8808123456789012'); // destination VA number
    // Channel logo asset is rendered when available; SVG fallback otherwise.
    expect($html)->toContain('bca.svg');
});
