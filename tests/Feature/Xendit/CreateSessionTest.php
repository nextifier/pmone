<?php

use App\Enums\Payment\CheckoutMethod;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('createSession posts a PAYMENT_LINK session and returns the payment link', function () {
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-abc123',
            'payment_link_url' => 'https://checkout.xendit.co/web/ps-abc123',
            'status' => 'ACTIVE',
        ], 201),
    ]);

    $reservation = Reservation::factory()->create([
        'total_amount' => 1500000,
        'guest_name' => 'Budi Santoso',
        'guest_email' => 'budi@example.com',
        // Not E.164 — must be dropped from the request.
        'guest_phone' => '08123456789',
    ]);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    $result = XenditService::forGateway($gateway)->createSession(
        $reservation,
        'https://app.test/success',
        'https://app.test/cancel',
    );

    expect($result['session_id'])->toBe('ps-abc123');
    expect($result['payment_url'])->toBe('https://checkout.xendit.co/web/ps-abc123');

    Http::assertSent(function ($request) use ($reservation) {
        $body = $request->data();

        return $request->url() === 'https://api.xendit.co/sessions'
            && $body['mode'] === 'PAYMENT_LINK'
            && $body['session_type'] === 'PAY'
            && $body['currency'] === 'IDR'
            && $body['country'] === 'ID'
            && $body['amount'] === 1500000
            && $body['reference_id'] === $reservation->reservation_number
            && $body['success_return_url'] === 'https://app.test/success'
            && $body['cancel_return_url'] === 'https://app.test/cancel'
            && isset($body['expires_at'])
            && ! isset($body['customer']['mobile_number']);
    });
});

test('createSession forwards a valid E.164 mobile number', function () {
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-e164',
            'payment_link_url' => 'https://checkout.xendit.co/web/ps-e164',
            'status' => 'ACTIVE',
        ], 201),
    ]);

    $reservation = Reservation::factory()->create([
        'total_amount' => 200000,
        'guest_phone' => '+6281234567890',
    ]);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    XenditService::forGateway($gateway)->createSession($reservation);

    Http::assertSent(fn ($request) => ($request->data()['customer']['mobile_number'] ?? null) === '+6281234567890');
});

test('createSession maps a 400 INVALID_URL response to a provider exception', function () {
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'error_code' => 'INVALID_URL',
            'message' => 'success_return_url is not a valid URL',
        ], 400),
    ]);

    $reservation = Reservation::factory()->create(['total_amount' => 100000]);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    XenditService::forGateway($gateway)->createSession(
        $reservation,
        'http://localhost:3000/hotels/success',
        'http://localhost:3000/hotels',
    );
})->throws(PaymentProviderException::class);

test('createSession maps a 401 response to a provider exception', function () {
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'error_code' => 'INVALID_API_KEY',
            'message' => 'API key is invalid',
        ], 401),
    ]);

    $reservation = Reservation::factory()->create(['total_amount' => 100000]);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    XenditService::forGateway($gateway)->createSession($reservation);
})->throws(PaymentProviderException::class);

test('createCheckout dispatches to the Sessions API for a sessions_payment_link gateway', function () {
    Http::fake([
        'api.xendit.co/sessions' => Http::response([
            'payment_session_id' => 'ps-dispatch',
            'payment_link_url' => 'https://checkout.xendit.co/web/ps-dispatch',
            'status' => 'ACTIVE',
        ], 201),
    ]);

    $reservation = Reservation::factory()->create(['total_amount' => 500000]);
    $gateway = ProjectPaymentGateway::factory()->sessionsPaymentLink()->create();

    $result = XenditService::forGateway($gateway)->createCheckout($reservation);

    expect($result['reference'])->toBe('ps-dispatch');
    expect($result['payment_url'])->toBe('https://checkout.xendit.co/web/ps-dispatch');
    expect($result['checkout_method'])->toBe('sessions_payment_link');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sessions'));
});

test('createCheckout throws for the not-yet-available components method', function () {
    $reservation = Reservation::factory()->create();
    $gateway = ProjectPaymentGateway::factory()->create([
        'checkout_method' => CheckoutMethod::SessionsComponents,
    ]);

    XenditService::forGateway($gateway)->createCheckout($reservation);
})->throws(PaymentProviderException::class);
