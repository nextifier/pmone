<?php

use App\Enums\ReservationStatus;
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
