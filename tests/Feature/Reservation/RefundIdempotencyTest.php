<?php

use App\Enums\ReservationStatus;
use App\Exceptions\Payment\PaymentProviderException;
use App\Jobs\Reservation\ProcessXenditRefundJob;
use App\Models\Hotel;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Xendit\XenditSdkException;

uses(RefreshDatabase::class);

/**
 * Attach an active Xendit gateway to the reservation's project so the refund
 * job can resolve credentials. Also binds a mock XenditService into the
 * container so forGateway() returns it instead of hitting the real Xendit API.
 *
 * Returns the mock so each test can set its own expectations.
 */
function bindMockXenditForReservation(Reservation $reservation): MockInterface
{
    $project = $reservation->event->project;
    ProjectPaymentGateway::factory()->create([
        'project_id' => $project->id,
        'provider' => 'xendit',
        'mode' => 'test',
        'is_active' => true,
    ]);

    $mock = Mockery::mock(XenditService::class);
    app()->instance(XenditService::class, $mock);

    return $mock;
}

test('refund job is skipped when xendit_refund_id already exists', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1000000,
        'xendit_invoice_id' => 'inv_already_refunded',
        'xendit_refund_id' => 'rfnd_existing_123',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldNotReceive('refundInvoice');

    $job = new ProcessXenditRefundJob($reservation->id, 500000.0, 'Duplicate retry test');
    $job->handle();

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBe('rfnd_existing_123');
    expect($reservation->status)->not->toBe(ReservationStatus::Refunded);
});

test('refund job returns early when reservation has no xendit_invoice_id', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1000000,
        'xendit_invoice_id' => null,
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldNotReceive('refundInvoice');

    $job = new ProcessXenditRefundJob($reservation->id, 500000.0, 'No invoice');
    $job->handle();

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBeNull();
});

test('refund job persists xendit_refund_id on success', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 2000000,
        'xendit_invoice_id' => 'inv_for_refund',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldReceive('refundInvoice')
        ->once()
        ->with('inv_for_refund', 1000000.0, 'Cancellation')
        ->andReturn('rfnd_new_456');

    $job = new ProcessXenditRefundJob($reservation->id, 1000000.0, 'Cancellation');
    $job->handle();

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBe('rfnd_new_456');
    expect($reservation->status)->toBe(ReservationStatus::Refunded);
    expect((float) $reservation->refund_amount)->toBe(1000000.0);
});

test('refund job swallows 4xx Xendit errors instead of retrying', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 2000000,
        'xendit_invoice_id' => 'inv_unrefundable_channel',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldReceive('refundInvoice')
        ->once()
        ->andThrow(new XenditSdkException(
            (object) ['message' => 'Refund request failed because refunds are not supported for this channel.'],
            '400',
            'Refund request failed because refunds are not supported for this channel.'
        ));

    $job = new ProcessXenditRefundJob($reservation->id, 1000000.0, 'Cancellation');

    // Should NOT throw — 4xx is permanent, retry won't help.
    $job->handle();

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBeNull()
        ->and($reservation->status)->not->toBe(ReservationStatus::Refunded);
});

test('refund job rethrows 5xx Xendit errors so queue retries', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 2000000,
        'xendit_invoice_id' => 'inv_xendit_down',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldReceive('refundInvoice')
        ->once()
        ->andThrow(new XenditSdkException(
            (object) ['message' => 'Internal server error'],
            '500',
            'Internal server error'
        ));

    $job = new ProcessXenditRefundJob($reservation->id, 1000000.0, 'Cancellation');

    expect(fn () => $job->handle())->toThrow(XenditSdkException::class);
});

test('refund job uses the QR Code refund endpoint for QRIS payments', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1160,
        'xendit_invoice_id' => 'inv_qris',
        'xendit_payment_id' => 'qrpy_f0798900',
        'payment_channel' => 'QRIS',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldNotReceive('refundInvoice');
    $mock->shouldReceive('refundQrPayment')
        ->once()
        ->with('qrpy_f0798900', 1160.0, 'Testing')
        ->andReturn([
            'id' => 'qrrf_new_789',
            'status' => 'PENDING',
            'refund_amount' => 1160,
            'channel_code' => 'ID_DANA',
        ]);

    $job = new ProcessXenditRefundJob($reservation->id, 1160.0, 'Testing');
    $job->handle();

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBe('qrrf_new_789')
        ->and($reservation->status)->toBe(ReservationStatus::Refunded)
        ->and((float) $reservation->refund_amount)->toBe(1160.0);
});

test('refund job flags manual refund when a QRIS reservation has no qrpy payment id', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1160,
        'xendit_invoice_id' => 'inv_qris_no_payment_id',
        'xendit_payment_id' => null,
        'payment_channel' => 'QRIS',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldNotReceive('refundInvoice');
    $mock->shouldNotReceive('refundQrPayment');

    $job = new ProcessXenditRefundJob($reservation->id, 1160.0, 'Testing');
    $job->handle();

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBeNull()
        ->and($reservation->status)->not->toBe(ReservationStatus::Refunded);
});

test('refund job swallows a 4xx QR refund error and leaves it for manual handling', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1160,
        'xendit_invoice_id' => 'inv_qris_unsupported_issuer',
        'xendit_payment_id' => 'qrpy_gopay',
        'payment_channel' => 'QRIS',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldReceive('refundQrPayment')
        ->once()
        ->andThrow(new PaymentProviderException(
            'Refund request failed because refunds are not supported for this channel.',
            'REFUND_NOT_SUPPORTED',
            400,
        ));

    $job = new ProcessXenditRefundJob($reservation->id, 1160.0, 'Testing');

    // Must NOT throw — a 4xx (unsupported QRIS issuer) is permanent.
    $job->handle();

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBeNull()
        ->and($reservation->status)->not->toBe(ReservationStatus::Refunded);
});

test('refund job rethrows a 5xx QR refund error so the queue retries', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1160,
        'xendit_invoice_id' => 'inv_qris_xendit_down',
        'xendit_payment_id' => 'qrpy_dana',
        'payment_channel' => 'QRIS',
    ]);

    $mock = bindMockXenditForReservation($reservation);
    $mock->shouldReceive('refundQrPayment')
        ->once()
        ->andThrow(new PaymentProviderException('Internal server error', 'QR_REFUND_FAILED', 503));

    $job = new ProcessXenditRefundJob($reservation->id, 1160.0, 'Testing');

    expect(fn () => $job->handle())->toThrow(PaymentProviderException::class);
});
