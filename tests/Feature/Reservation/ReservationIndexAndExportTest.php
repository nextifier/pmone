<?php

use App\Enums\ReservationStatus;
use App\Exports\ReservationsExport;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'reservations.read',
        'reservations.export',
        'reservations.delete',
        'reservations.send_voucher',
    ] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();

    $this->reader = User::factory()->create(['email_verified_at' => now()]);
    $this->reader->givePermissionTo(['reservations.read', 'reservations.export']);

    $this->stranger = User::factory()->create(['email_verified_at' => now()]);
});

test('admin can list reservations for an event', function () {
    Reservation::factory()->count(3)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->reader);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('reservation list is filtered by status', function () {
    Reservation::factory()->count(2)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'status' => ReservationStatus::PendingPayment,
    ]);
    Reservation::factory()->paid()->count(1)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->reader);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations?filter_status=paid");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'paid');
});

test('reservation list is filtered by search', function () {
    Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Alice Wonderland',
        'guest_email' => 'alice@test.com',
    ]);
    Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_name' => 'Bob Brown',
        'guest_email' => 'bob@test.com',
    ]);

    $this->actingAs($this->reader);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations?filter_search=alice");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.guest_name', 'Alice Wonderland');
});

test('reservation list pagination respects per_page', function () {
    Reservation::factory()->count(5)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->reader);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations?per_page=2&page=2");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2);
});

test('reservation list exposes payment mode and provider from the linked gateway', function () {
    $liveGateway = ProjectPaymentGateway::factory()->create(['mode' => 'live', 'provider' => 'xendit']);
    $testGateway = ProjectPaymentGateway::factory()->create(['mode' => 'test', 'provider' => 'xendit']);

    Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_gateway_id' => $liveGateway->id,
        'guest_name' => 'Live Guest',
    ]);
    Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_gateway_id' => $testGateway->id,
        'guest_name' => 'Test Guest',
    ]);
    Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'payment_gateway_id' => null,
        'guest_name' => 'Manual Guest',
    ]);

    $this->actingAs($this->reader);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations");

    $response->assertSuccessful();

    $byName = collect($response->json('data'))->keyBy('guest_name');

    expect($byName['Live Guest']['payment_mode'])->toBe('live');
    expect($byName['Live Guest']['payment_provider'])->toBe('xendit');
    expect($byName['Test Guest']['payment_mode'])->toBe('test');
    expect($byName['Test Guest']['payment_provider'])->toBe('xendit');
    expect($byName['Manual Guest']['payment_mode'])->toBeNull();
    expect($byName['Manual Guest']['payment_provider'])->toBeNull();
});

test('user without reservations.read permission cannot list', function () {
    $this->actingAs($this->stranger);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations");

    $response->assertForbidden();
});

test('admin can show single reservation', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->reader);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}");

    $response->assertSuccessful()
        ->assertJsonPath('data.reservation_number', $reservation->reservation_number);
});

test('show 404 when reservation belongs to different event', function () {
    $otherEvent = Event::factory()->create();
    $otherHotel = Hotel::factory()->withEvent($otherEvent)->create();
    $reservation = Reservation::factory()->create([
        'hotel_id' => $otherHotel->id,
        'event_id' => $otherEvent->id,
    ]);

    $this->actingAs($this->reader);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}");

    $response->assertNotFound();
});

test('export endpoint returns xlsx file', function () {
    Reservation::factory()->count(2)->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->reader);

    $response = $this->get("/api/events/{$this->event->id}/reservations/export");

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml');
});

test('export headings exclude earliest/latest and include all new audit columns', function () {
    $export = new ReservationsExport;

    $headings = $export->headings();

    expect($headings)
        ->not->toContain('Check-in Earliest')
        ->not->toContain('Check-out Latest')
        ->toContain('Notes')
        ->toContain('Special Request')
        ->toContain('Total Nights')
        ->toContain('Created By')
        ->toContain('Surcharge')
        ->toContain('Penalty')
        ->toContain('Discount')
        ->toContain('Promo Code')
        ->toContain('Payment Channel')
        ->toContain('Payment Destination')
        ->toContain('Xendit Invoice ID')
        ->toContain('Cancellation Reason')
        ->toContain('Refund Reason');
});

test('export applies money format to currency columns and text format to identity number', function () {
    $export = new ReservationsExport;

    $formats = $export->columnFormats();

    // Identity Number column (L) must be text to preserve 16-digit NIK precision
    expect($formats['L'])->toBe(NumberFormat::FORMAT_TEXT);

    // Phone column (J) must use phone format
    expect($formats['J'])->toBe('+#');

    // Currency columns must use thousand-separator format
    foreach (['R', 'S', 'T', 'U', 'V', 'X', 'Y', 'Z', 'AI'] as $col) {
        expect($formats[$col])->toBe('#,##0');
    }
});

test('export map returns cleaned phone, financial, payment ops, reasons, and created_by fields', function () {
    $creator = User::factory()->create(['name' => 'Admin Staff']);

    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_phone' => '+62 812-1234-5678',
        'guest_identity_number' => '3173050601010001',
        'notes' => 'Internal staff note',
        'special_request' => 'Late check-in please',
        'created_by' => $creator->id,
        'surcharge_amount' => 50000,
        'penalty_amount' => 25000,
        'discount_amount' => 100000,
        'promo_code_applied' => 'WELCOME10',
        'xendit_invoice_id' => 'inv_abc123',
        'payment_channel' => 'BCA',
        'payment_destination' => '1234567890',
        'cancellation_reason' => 'Guest changed plans',
        'refund_reason' => 'Refund per policy',
    ]);

    $reservation->load(['hotel', 'event', 'creator', 'items.roomType', 'transfers']);

    $export = new ReservationsExport;
    $row = $export->map($reservation);
    $headings = $export->headings();

    $assoc = array_combine($headings, $row);

    expect($assoc['Guest Phone'])->toBe('6281212345678');
    expect($assoc['Identity Number'])->toBe('3173050601010001');
    expect($assoc['Notes'])->toBe('Internal staff note');
    expect($assoc['Special Request'])->toBe('Late check-in please');
    expect($assoc['Created By'])->toBe('Admin Staff');
    expect($assoc['Surcharge'])->toBe(50000.0);
    expect($assoc['Penalty'])->toBe(25000.0);
    expect($assoc['Discount'])->toBe(100000.0);
    expect($assoc['Promo Code'])->toBe('WELCOME10');
    expect($assoc['Xendit Invoice ID'])->toBe('inv_abc123');
    expect($assoc['Payment Channel'])->toBe('BCA');
    expect($assoc['Payment Destination'])->toBe('1234567890');
    expect($assoc['Cancellation Reason'])->toBe('Guest changed plans');
    expect($assoc['Refund Reason'])->toBe('Refund per policy');
    expect($assoc)->toHaveKey('Total Nights');
    expect($assoc)->not->toHaveKey('Check-in Earliest');
    expect($assoc)->not->toHaveKey('Check-out Latest');
});

test('export map renders nullable financial and ops fields as dash placeholders', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'created_by' => null,
        'promo_code_applied' => null,
        'xendit_invoice_id' => null,
        'payment_channel' => null,
        'payment_destination' => null,
        'cancellation_reason' => null,
        'refund_reason' => null,
    ]);

    $reservation->load(['hotel', 'event', 'creator', 'items.roomType', 'transfers']);

    $export = new ReservationsExport;
    $assoc = array_combine($export->headings(), $export->map($reservation));

    expect($assoc['Created By'])->toBe('-');
    expect($assoc['Promo Code'])->toBe('-');
    expect($assoc['Xendit Invoice ID'])->toBe('-');
    expect($assoc['Payment Channel'])->toBe('-');
    expect($assoc['Payment Destination'])->toBe('-');
    expect($assoc['Cancellation Reason'])->toBe('-');
    expect($assoc['Refund Reason'])->toBe('-');
});

test('export forbidden without reservations.export permission', function () {
    $userNoExport = User::factory()->create(['email_verified_at' => now()]);
    $userNoExport->givePermissionTo(['reservations.read']);

    $this->actingAs($userNoExport);

    $response = $this->get("/api/events/{$this->event->id}/reservations/export");

    $response->assertForbidden();
});

test('activity log requires reservations.read permission', function () {
    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $this->actingAs($this->stranger);

    $response = $this->getJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/activity");

    $response->assertForbidden();
});
