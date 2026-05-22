<?php

use App\Exceptions\Payment\PaymentProviderException;
use App\Models\ProjectPaymentGateway;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * QRIS payments cannot be refunded through the unified /refunds API — Xendit
 * rejects them with REFUND_NOT_SUPPORTED. They must go through the dedicated
 * QR Code Refund endpoint: POST /qr_codes/payments/{qrpy_id}/refunds.
 */
function qrRefundService(): XenditService
{
    $gateway = ProjectPaymentGateway::factory()->default()->create([
        'provider' => 'xendit',
        'mode' => 'test',
        'secret_key' => 'xnd_development_test_key',
    ]);

    return new XenditService($gateway);
}

test('refundQrPayment posts to the QR Code refund endpoint and parses the response', function () {
    Http::fake([
        'api.xendit.co/qr_codes/payments/*/refunds' => Http::response([
            'id' => 'qrrf_abc123',
            'qrpy_id' => 'qrpy_f0798900',
            'status' => 'PENDING',
            'currency' => 'IDR',
            'payment_amount' => 1160,
            'refund_amount' => 1160,
            'channel_code' => 'ID_DANA',
        ], 200),
    ]);

    $result = qrRefundService()->refundQrPayment('qrpy_f0798900', 1160.0, 'Testing');

    expect($result)->toMatchArray([
        'id' => 'qrrf_abc123',
        'status' => 'PENDING',
        'refund_amount' => 1160,
        'channel_code' => 'ID_DANA',
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/qr_codes/payments/qrpy_f0798900/refunds')
            && $request['amount'] === 1160
            && $request['reason'] === 'CANCELLATION';
    });
});

test('refundQrPayment normalizes free-text reason to a Xendit enum value', function () {
    Http::fake([
        'api.xendit.co/*' => Http::response(['id' => 'qrrf_x', 'status' => 'PENDING'], 200),
    ]);

    qrRefundService()->refundQrPayment('qrpy_x', 1160.0, 'requested_by_customer');

    Http::assertSent(fn ($request) => $request['reason'] === 'REQUESTED_BY_CUSTOMER');
});

test('refundQrPayment throws PaymentProviderException carrying the Xendit error code and 4xx status', function () {
    Http::fake([
        'api.xendit.co/*' => Http::response([
            'error_code' => 'REFUND_NOT_SUPPORTED',
            'message' => 'Refund request failed because refunds are not supported for this channel.',
        ], 400),
    ]);

    try {
        qrRefundService()->refundQrPayment('qrpy_unsupported', 1160.0, 'Testing');
        $this->fail('Expected PaymentProviderException to be thrown.');
    } catch (PaymentProviderException $e) {
        expect($e->errorCode)->toBe('REFUND_NOT_SUPPORTED')
            ->and($e->httpStatus)->toBe(400)
            ->and($e->getMessage())->toContain('not supported for this channel');
    }
});

test('refundQrPayment surfaces a 5xx as a retryable PaymentProviderException', function () {
    Http::fake([
        'api.xendit.co/*' => Http::response(['message' => 'Internal server error'], 500),
    ]);

    try {
        qrRefundService()->refundQrPayment('qrpy_down', 1160.0, 'Testing');
        $this->fail('Expected PaymentProviderException to be thrown.');
    } catch (PaymentProviderException $e) {
        expect($e->httpStatus)->toBe(500);
    }
});
