<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bouncerReservation(array $overrides = []): Reservation
{
    return Reservation::factory()->create(array_merge([
        'reservation_number' => 'HTL-BOUNCE-1',
        'return_origin' => 'https://iicc.askindo.id',
        'status' => ReservationStatus::PendingPayment,
    ], $overrides));
}

it('redirects a settled payment to the originating site success page', function () {
    bouncerReservation(['reservation_number' => 'HTL-BOUNCE-PAID']);

    $resp = $this->get('/payment/redirect?order_id=HTL-BOUNCE-PAID&transaction_status=settlement');

    $resp->assertRedirect();
    expect($resp->headers->get('Location'))
        ->toStartWith('https://iicc.askindo.id/hotels/success?ref=HTL-BOUNCE-PAID&token=');
});

it('redirects an explicit failure (expire/deny) to the originating failed page', function () {
    bouncerReservation(['reservation_number' => 'HTL-BOUNCE-FAIL']);

    $this->get('/payment/redirect?order_id=HTL-BOUNCE-FAIL&transaction_status=expire')
        ->assertRedirect('https://iicc.askindo.id/hotels?failed=HTL-BOUNCE-FAIL');
});

it('sends a pending Midtrans status to the receipt (success) page', function () {
    bouncerReservation(['reservation_number' => 'HTL-BOUNCE-PEND']);

    $resp = $this->get('/payment/redirect?order_id=HTL-BOUNCE-PEND&transaction_status=pending');

    expect($resp->headers->get('Location'))->toStartWith('https://iicc.askindo.id/hotels/success');
});

it('honours the Xendit result hint: success -> receipt, failed -> failed page', function () {
    bouncerReservation(['reservation_number' => 'HTL-BOUNCE-XOK']);
    bouncerReservation(['reservation_number' => 'HTL-BOUNCE-XFAIL']);

    expect($this->get('/payment/redirect?order_id=HTL-BOUNCE-XOK&result=success')->headers->get('Location'))
        ->toStartWith('https://iicc.askindo.id/hotels/success');

    $this->get('/payment/redirect?order_id=HTL-BOUNCE-XFAIL&result=failed')
        ->assertRedirect('https://iicc.askindo.id/hotels?failed=HTL-BOUNCE-XFAIL');
});

it('uses the reservation paid status when no transaction_status param is present', function () {
    bouncerReservation([
        'reservation_number' => 'HTL-BOUNCE-PAIDSTATUS',
        'status' => ReservationStatus::Paid,
        'paid_at' => now(),
    ]);

    $resp = $this->get('/payment/redirect?order_id=HTL-BOUNCE-PAIDSTATUS');

    expect($resp->headers->get('Location'))->toStartWith('https://iicc.askindo.id/hotels/success');
});

it('strips a retry ~N suffix from order_id before resolving the reservation', function () {
    bouncerReservation(['reservation_number' => 'HTL-BOUNCE-RETRY']);

    $resp = $this->get('/payment/redirect?order_id=HTL-BOUNCE-RETRY~2&transaction_status=settlement');

    expect($resp->headers->get('Location'))
        ->toStartWith('https://iicc.askindo.id/hotels/success?ref=HTL-BOUNCE-RETRY&token=');
});

it('falls back to FRONTEND_URL for an unknown order', function () {
    $this->get('/payment/redirect?order_id=HTL-DOESNOTEXIST&transaction_status=settlement')
        ->assertRedirect(rtrim(config('app.frontend_url'), '/'));
});

it('falls back to FRONTEND_URL when order_id is missing', function () {
    $this->get('/payment/redirect')
        ->assertRedirect(rtrim(config('app.frontend_url'), '/'));
});
