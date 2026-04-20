<?php

use App\Enums\ReservationStatus;
use App\Jobs\Reservation\ProcessXenditRefundJob;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('refund job is skipped when xendit_refund_id already exists', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $hotel->id,
        'total_amount' => 1000000,
        'xendit_invoice_id' => 'inv_already_refunded',
        'xendit_refund_id' => 'rfnd_existing_123',
    ]);

    $xendit = Mockery::mock(XenditService::class);
    $xendit->shouldNotReceive('refundInvoice');

    $job = new ProcessXenditRefundJob($reservation->id, 500000.0, 'Duplicate retry test');
    $job->handle($xendit);

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

    $xendit = Mockery::mock(XenditService::class);
    $xendit->shouldNotReceive('refundInvoice');

    $job = new ProcessXenditRefundJob($reservation->id, 500000.0, 'No invoice');
    $job->handle($xendit);

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

    $xendit = Mockery::mock(XenditService::class);
    $xendit->shouldReceive('refundInvoice')
        ->once()
        ->with('inv_for_refund', 1000000.0, 'Cancellation')
        ->andReturn('rfnd_new_456');

    $job = new ProcessXenditRefundJob($reservation->id, 1000000.0, 'Cancellation');
    $job->handle($xendit);

    $reservation->refresh();
    expect($reservation->xendit_refund_id)->toBe('rfnd_new_456');
    expect($reservation->status)->toBe(ReservationStatus::Refunded);
    expect((float) $reservation->refund_amount)->toBe(1000000.0);
});
