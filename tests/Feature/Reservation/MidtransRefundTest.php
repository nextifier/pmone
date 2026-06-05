<?php

use App\Enums\ReservationStatus;
use App\Jobs\Reservation\ProcessMidtransRefundJob;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create(['status' => 'active']);
    $this->event = Event::factory()->withoutPaymentGateway()->create(['project_id' => $this->project->id]);
    $this->hotel = Hotel::factory()->create();
    $this->gateway = ProjectPaymentGateway::factory()->for($this->project)->midtrans()->create([
        'is_active' => true,
        'secret_key' => 'SB-Mid-server-REFUNDKEY1234567890',
    ]);
});

function paidMidtransReservation($ctx, array $overrides = []): Reservation
{
    return Reservation::factory()->create(array_merge([
        'event_id' => $ctx->event->id,
        'hotel_id' => $ctx->hotel->id,
        'status' => ReservationStatus::Paid,
        'paid_at' => now(),
        'payment_method' => 'midtrans',
        'payment_gateway_id' => $ctx->gateway->id,
        'payment_channel' => 'GOPAY',
        'total_amount' => 100000,
    ], $overrides));
}

it('refunds a refundable Midtrans reservation and stores the refund key', function () {
    Http::fake(['api.sandbox.midtrans.com/v2/*/refund/online/direct' => Http::response([
        'status_code' => '200', 'refund_key' => 'rfd-1',
    ], 200)]);

    $r = paidMidtransReservation($this, ['reservation_number' => 'HTL-RFD-OK']);

    ProcessMidtransRefundJob::dispatchSync($r->id, 100000, 'Customer cancelled');

    $r->refresh();
    expect($r->status)->toBe(ReservationStatus::Refunded)
        ->and($r->xendit_refund_id)->toBe('rfd-1')
        ->and((float) $r->refund_amount)->toBe(100000.0);
});

it('leaves a non-refundable channel for manual refund without double-charging', function () {
    Http::fake(['api.sandbox.midtrans.com/v2/*/refund/online/direct' => Http::response([
        'status_code' => '412', 'status_message' => 'cannot refund',
    ], 200)]);

    $r = paidMidtransReservation($this, [
        'reservation_number' => 'HTL-RFD-MANUAL',
        'status' => ReservationStatus::Cancelled,
        'payment_channel' => 'BCA',
    ]);

    ProcessMidtransRefundJob::dispatchSync($r->id, 100000, 'x');

    $r->refresh();
    // No refund id -> outstanding manual refund; reservation untouched.
    expect($r->xendit_refund_id)->toBeNull()
        ->and($r->status)->toBe(ReservationStatus::Cancelled);
});

it('is idempotent when the reservation is already refunded', function () {
    Http::fake();

    $r = paidMidtransReservation($this, [
        'reservation_number' => 'HTL-RFD-DONE',
        'xendit_refund_id' => 'already',
    ]);

    ProcessMidtransRefundJob::dispatchSync($r->id, 100000, 'x');

    Http::assertNothingSent();
    expect($r->fresh()->xendit_refund_id)->toBe('already');
});
