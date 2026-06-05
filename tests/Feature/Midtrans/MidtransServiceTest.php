<?php

use App\DTOs\Payment\TransactionQuery;
use App\Enums\ReservationStatus;
use App\Exceptions\Payment\PaymentProviderException;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Services\Midtrans\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function midtransGateway(array $overrides = []): ProjectPaymentGateway
{
    return ProjectPaymentGateway::factory()->midtrans()->create(array_merge([
        'secret_key' => 'SB-Mid-server-KNOWNKEY1234567890',
    ], $overrides));
}

it('creates a Snap checkout and returns the redirect url', function () {
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-abc',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-abc',
        ], 201),
    ]);

    $gateway = midtransGateway(['mode' => 'test']);
    $reservation = Reservation::factory()->create([
        'reservation_number' => 'HTL-MID-0001',
        'total_amount' => 1500000,
        'guest_name' => 'Budi',
        'guest_email' => 'budi@example.com',
        'guest_phone' => '08123456789',
    ]);

    $result = MidtransService::forGateway($gateway)
        ->createCheckout($reservation, 'https://pmone.test/hotels/success', 'https://pmone.test/hotels?failed=x');

    expect($result)->toMatchArray([
        'reference' => 'snap-token-abc',
        'payment_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-abc',
        'checkout_method' => 'snap',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->hasHeader('Authorization')
            && $body['transaction_details']['order_id'] === 'HTL-MID-0001'
            && $body['transaction_details']['gross_amount'] === 1500000
            && $body['callbacks']['finish'] === 'https://pmone.test/hotels/success'
            && ! array_key_exists('item_details', $body);
    });
});

it('overrides the notification URL per transaction when configured', function () {
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 't', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/t',
        ], 201),
    ]);

    $gateway = midtransGateway(['config' => ['notification_override_url' => 'https://tunnel.test/api/webhooks/midtrans']]);
    $reservation = Reservation::factory()->create(['total_amount' => 100000]);

    MidtransService::forGateway($gateway)->createCheckout($reservation, 'https://pmone.test/s');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Override-Notification', 'https://tunnel.test/api/webhooks/midtrans'));
});

it('uses the production host when the gateway mode is live', function () {
    Http::fake([
        'app.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'tok', 'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/tok',
        ], 201),
    ]);

    $gateway = midtransGateway(['mode' => 'live', 'secret_key' => 'Mid-server-LIVEKEY1234567890']);
    $reservation = Reservation::factory()->create(['total_amount' => 100000]);

    MidtransService::forGateway($gateway)->createCheckout($reservation, 'https://pmone.test/s');

    Http::assertSent(fn ($request) => $request->url() === 'https://app.midtrans.com/snap/v1/transactions');
});

it('maps a Snap 401 to a PaymentProviderException', function () {
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['status_code' => '401', 'status_message' => 'unauthorized'], 401),
    ]);

    $gateway = midtransGateway();
    $reservation = Reservation::factory()->create(['total_amount' => 100000]);

    MidtransService::forGateway($gateway)->createCheckout($reservation, 'https://pmone.test/s');
})->throws(PaymentProviderException::class);

it('verifies a correct SHA512 signature and rejects a wrong one', function () {
    $serverKey = 'SB-Mid-server-KNOWNKEY1234567890';
    $service = MidtransService::forGateway(midtransGateway(['secret_key' => $serverKey]));

    $payload = ['order_id' => 'HTL-1', 'status_code' => '200', 'gross_amount' => '100000.00'];
    $payload['signature_key'] = hash('sha512', 'HTL-1200100000.00'.$serverKey);

    expect($service->verifySignature($payload))->toBeTrue();

    $payload['signature_key'] = 'deadbeef';
    expect($service->verifySignature($payload))->toBeFalse();
});

it('fails signature verification when unbound', function () {
    expect((new MidtransService)->verifySignature([
        'order_id' => 'x', 'status_code' => '200', 'gross_amount' => '1', 'signature_key' => 'y',
    ]))->toBeFalse();
});

it('testCredentials rejects a 401 (invalid server key)', function () {
    Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response(['status_code' => '401'], 401)]);

    $result = (new MidtransService)->testCredentials('SB-Mid-server-x', 'test');

    expect($result['success'])->toBeFalse()
        ->and($result['error_code'])->toBe('PAYMENT_GATEWAY_MISCONFIGURED');
});

it('testCredentials accepts a 404 (valid key, order simply not found)', function () {
    Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404)]);

    expect((new MidtransService)->testCredentials('SB-Mid-server-x', 'test')['success'])->toBeTrue();
});

it('rejects binding a non-midtrans gateway', function () {
    MidtransService::forGateway(ProjectPaymentGateway::factory()->create(['provider' => 'xendit']));
})->throws(InvalidArgumentException::class);

it('normalises payment channels to the receipt vocabulary', function () {
    $service = MidtransService::forGateway(midtransGateway());

    expect($service->resolveChannel(['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bca', 'va_number' => '123']]]))->toBe('BCA')
        ->and($service->resolveChannel(['payment_type' => 'qris']))->toBe('QRIS')
        ->and($service->resolveChannel(['payment_type' => 'gopay']))->toBe('GOPAY')
        ->and($service->resolveChannel(['payment_type' => 'shopeepay']))->toBe('SHOPEEPAY')
        ->and($service->resolveChannel(['payment_type' => 'credit_card']))->toBe('CREDIT_CARD')
        ->and($service->resolveChannel(['payment_type' => 'echannel']))->toBe('MANDIRI');
});

it('exposes Invoicing + Refunds + Transactions + Settlement capabilities (no Balance)', function () {
    $service = MidtransService::forGateway(midtransGateway());

    expect(array_map(fn ($c) => $c->value, $service->capabilities()))
        ->toBe(['invoicing', 'refunds', 'transactions', 'settlement']);
});

it('lists local reservations as transactions for this gateway only', function () {
    $gateway = midtransGateway();

    Reservation::factory()->count(2)->create([
        'payment_gateway_id' => $gateway->id,
        'status' => ReservationStatus::Paid,
        'paid_at' => now(),
        'total_amount' => 50000,
    ]);
    Reservation::factory()->create(['payment_gateway_id' => null, 'status' => ReservationStatus::Paid]);

    $page = MidtransService::forGateway($gateway)->listTransactions(new TransactionQuery(limit: 10, status: 'success'));

    expect($page->entries)->toHaveCount(2)
        ->and($page->entries[0]->type)->toBe('payment')
        ->and($page->entries[0]->status)->toBe('success')
        ->and($page->entries[0]->reference)->not->toBeEmpty();
});

it('derives a settlement summary from local paid reservations', function () {
    $gateway = midtransGateway();

    Reservation::factory()->create([
        'payment_gateway_id' => $gateway->id, 'status' => ReservationStatus::Paid,
        'paid_at' => now()->subDays(3), 'total_amount' => 100000,
    ]);
    Reservation::factory()->create([
        'payment_gateway_id' => $gateway->id, 'status' => ReservationStatus::Paid,
        'paid_at' => now(), 'total_amount' => 30000,
    ]);

    $summary = MidtransService::forGateway($gateway)
        ->getSettlementSummary(now()->subDays(10)->toDateString(), now()->toDateString());

    expect($summary->settledCount)->toBe(1)
        ->and((float) $summary->settledAmount)->toBe(100000.0)
        ->and($summary->pendingCount)->toBe(1)
        ->and((float) $summary->pendingAmount)->toBe(30000.0);
});

it('refunds a settled transaction (online/direct) and returns the refund key', function () {
    Http::fake([
        'api.sandbox.midtrans.com/v2/*/refund/online/direct' => Http::response([
            'status_code' => '200', 'status_message' => 'Refund processed', 'refund_key' => 'rfd-key-1',
        ], 200),
    ]);

    $service = MidtransService::forGateway(midtransGateway());

    expect($service->refund('HTL-RFD-1', 100000, 'Customer cancelled'))->toBe('rfd-key-1');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/HTL-RFD-1/refund/online/direct')
        && $r->data()['amount'] === 100000
        && $r->data()['refund_key'] === 'pmone-rfd-HTL-RFD-1');
});

it('maps a Midtrans 412 refund rejection to an unrecoverable (422) exception', function () {
    Http::fake([
        'api.sandbox.midtrans.com/v2/*/refund/online/direct' => Http::response([
            'status_code' => '412', 'status_message' => 'cannot refund this channel',
        ], 200),
    ]);

    $caught = null;
    try {
        MidtransService::forGateway(midtransGateway())->refund('HTL-RFD-2', 100000, 'x');
    } catch (PaymentProviderException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->httpStatus)->toBe(422);
});
