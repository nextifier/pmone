<?php

use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Jobs\Reservation\SendReservationWhatsAppJob;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('dispatches the whatsapp job when enabled and a reservation is marked paid', function () {
    config()->set('services.whatsapp.enabled', true);
    Bus::fake();

    $reservation = Reservation::factory()->create();

    app(ReservationService::class)->markAsPaid($reservation);

    Bus::assertDispatchedTimes(SendReservationWhatsAppJob::class, 1);
    Bus::assertDispatched(
        SendReservationWhatsAppJob::class,
        fn (SendReservationWhatsAppJob $job) => $job->reservationId === $reservation->id,
    );
});

it('does not dispatch the whatsapp job again on a duplicate paid webhook', function () {
    config()->set('services.whatsapp.enabled', true);
    Bus::fake();

    $reservation = Reservation::factory()->create();
    $service = app(ReservationService::class);

    $service->markAsPaid($reservation);
    $service->markAsPaid($reservation->fresh());

    Bus::assertDispatchedTimes(SendReservationWhatsAppJob::class, 1);
});

it('never dispatches the whatsapp job while the feature flag is off', function () {
    config()->set('services.whatsapp.enabled', false);
    Bus::fake();

    $reservation = Reservation::factory()->create();

    app(ReservationService::class)->markAsPaid($reservation);

    // The booking-received (email) job still fires; only WhatsApp is gated.
    Bus::assertDispatched(SendBookingReceivedJob::class);
    Bus::assertNotDispatched(SendReservationWhatsAppJob::class);
});
