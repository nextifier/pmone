<?php

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Services\Reservation\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

beforeEach(function () {
    AppSetting::set('branding', [
        'company_name' => 'PM One Test',
        'address' => 'Jakarta',
        'email' => 'test@pmone.id',
    ]);

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);
    $this->gateway = ProjectPaymentGateway::factory()->default()->create(['project_id' => $this->project->id]);
});

test('invoice view renders only the enabled payment channel logos for the gateway', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true],
            ['channel_code' => 'QRIS', 'is_activated' => true],
            ['channel_code' => 'BRI', 'is_activated' => false],
            ['channel_code' => 'MANDIRI', 'is_activated' => false],
        ], 200),
    ]);

    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_gateway_id' => $this->gateway->id,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    $service = app(DocumentService::class);
    $logos = $service->resolveEnabledPaymentLogos($reservation->fresh(['paymentGateway']));

    $html = View::make('pdf.reservation.invoice', [
        'r' => $reservation->fresh(['hotel', 'event', 'items.roomType', 'transfers']),
        'branding' => $service->getBranding($reservation),
        'invoiceNumber' => $service->buildInvoiceNumber($reservation),
        'enabledPaymentLogos' => $logos,
    ])->render();

    expect($html)->toContain('bca.svg');
    expect($html)->toContain('qris.svg');
    expect($html)->not->toContain('payment-methods/bri.svg');
    expect($html)->not->toContain('payment-methods/mandiri.svg');
});
