<?php

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['reservations.read', 'reservations.view_documents'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    AppSetting::set('branding', [
        'company_name' => 'PM One Test',
        'address' => 'Jakarta',
        'email' => 'test@pmone.id',
    ]);

    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id]);
});

test('admin can download invoice pdf', function () {
    $reservation = Reservation::factory()->create(['hotel_id' => $this->hotel->id, 'event_id' => $this->event->id]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    $response = $this->get("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/invoice.pdf");

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('admin cannot download receipt before payment', function () {
    $reservation = Reservation::factory()->create(['hotel_id' => $this->hotel->id, 'event_id' => $this->event->id]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    $response = $this->get("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/receipt.pdf");

    $response->assertStatus(422);
});

test('admin can download receipt after payment', function () {
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $this->hotel->id, 'event_id' => $this->event->id]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    $response = $this->get("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/receipt.pdf");

    $response->assertSuccessful();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

function renderInvoiceHtml(Reservation $reservation): string
{
    $reservation->loadMissing(['hotel', 'event', 'items.roomType', 'transfers', 'adjustments']);

    return view('pdf.reservation.invoice', [
        'r' => $reservation,
        'branding' => [],
        'invoiceNumber' => 'INV/TEST/0001',
        'enabledPaymentLogos' => [],
    ])->render();
}

test('invoice pdf shows the pay button while pending payment', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_url' => 'https://checkout.xendit.co/web/test-invoice',
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    expect(renderInvoiceHtml($reservation))->toContain('Click here to pay');
});

test('invoice pdf hides the pay button once paid', function () {
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_url' => 'https://checkout.xendit.co/web/test-invoice',
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $this->room->id,
    ]);

    expect(renderInvoiceHtml($reservation))->not->toContain('Click here to pay');
});

test('invoice pdf shows tax and service charge percentages', function () {
    $hotel = Hotel::factory()->withEvent($this->event)->create([
        'tax_percentage' => 11,
        'service_charge_percentage' => 5,
    ]);
    $room = RoomType::factory()->create(['hotel_id' => $hotel->id]);
    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'event_id' => $this->event->id,
        'service_charge_amount' => 32500,
    ]);
    ReservationItem::factory()->create([
        'reservation_id' => $reservation->id,
        'room_type_id' => $room->id,
    ]);

    expect(renderInvoiceHtml($reservation))
        ->toContain('Tax (PPN 11%)')
        ->toContain('Service Charge (5%)');
});
