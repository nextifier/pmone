<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->gateway = ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();

    $this->reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'status' => 'pending_payment',
        'payment_gateway_id' => $this->gateway->id,
        'xendit_invoice_id' => 'inv_card_test',
    ]);
});

test('markAsPaid resolves the real card network from the credit card charge', function () {
    Http::fake([
        'api.xendit.co/credit_card_charges/*' => Http::response(['card_brand' => 'MASTERCARD'], 200),
    ]);

    app(ReservationService::class)->markAsPaid($this->reservation, [
        'id' => 'inv_card_test',
        'payment_channel' => 'CREDIT_CARD',
        'credit_card_charge_id' => 'cc_charge_abc',
    ]);

    expect($this->reservation->fresh()->payment_channel)->toBe('MASTERCARD');
});

test('markAsPaid keeps CREDIT_CARD when the card brand cannot be resolved', function () {
    Http::fake([
        'api.xendit.co/credit_card_charges/*' => Http::response('not found', 404),
    ]);

    app(ReservationService::class)->markAsPaid($this->reservation, [
        'id' => 'inv_card_test',
        'payment_channel' => 'CREDIT_CARD',
        'credit_card_charge_id' => 'cc_charge_abc',
    ]);

    expect($this->reservation->fresh()->payment_channel)->toBe('CREDIT_CARD');
});

test('markAsPaid leaves non-card channels untouched', function () {
    app(ReservationService::class)->markAsPaid($this->reservation, [
        'id' => 'inv_card_test',
        'payment_channel' => 'BCA',
        'payment_destination' => '8808123456',
    ]);

    expect($this->reservation->fresh()->payment_channel)->toBe('BCA');
});
