<?php

use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Jobs\Reservation\SendCancellationJob;
use App\Jobs\Reservation\SendStaffReservationNotificationJob;
use App\Mail\Reservation\StaffReservationNotificationMail;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Configure the reservation's owning project with hotel notification recipients.
 */
function configureHotelNotification(Reservation $reservation, array $config): void
{
    $project = $reservation->event->project;
    $settings = $project->settings ?? [];
    data_set($settings, 'website_settings.hotels.notification_email', $config);
    $project->update(['settings' => $settings]);
}

/**
 * Configure the reservation's owning project with a custom subject template
 * for one of the `email_subjects.*` keys.
 */
function configureEmailSubjectTemplate(Reservation $reservation, string $key, string $template): void
{
    $project = $reservation->event->project;
    $settings = $project->settings ?? [];
    data_set($settings, "website_settings.email_subjects.{$key}", $template);
    $project->update(['settings' => $settings]);
}

it('emails staff with a custom subject template when configured', function () {
    Mail::fake();

    $reservation = Reservation::factory()->paid()->create();
    configureHotelNotification($reservation, [
        'to' => ['staff@example.com'],
        'cc' => ['cc@example.com'],
        'bcc' => ['bcc@example.com'],
    ]);
    configureEmailSubjectTemplate(
        $reservation,
        'staff_confirmed',
        'Booking {status} for {project}: {reservation_number}',
    );

    (new SendStaffReservationNotificationJob($reservation->id, 'confirmed'))->handle();

    $projectName = $reservation->event->project->name;

    Mail::assertSent(StaffReservationNotificationMail::class, function ($mail) use ($reservation, $projectName) {
        $mail->render(); // fails the test if the email template is broken

        return $mail->hasTo('staff@example.com')
            && $mail->hasCc('cc@example.com')
            && $mail->hasBcc('bcc@example.com')
            && $mail->hasReplyTo($reservation->guest_email)
            && $mail->emailSubject === "Booking Confirmed for {$projectName}: {$reservation->reservation_number}";
    });
});

it('emails staff with the default subject when a booking is cancelled', function () {
    Mail::fake();

    $reservation = Reservation::factory()->cancelled()->create();
    configureHotelNotification($reservation, [
        'to' => ['staff@example.com'],
        'cc' => [],
        'bcc' => [],
    ]);

    (new SendStaffReservationNotificationJob($reservation->id, 'cancelled'))->handle();

    $projectName = $reservation->event->project->name;

    Mail::assertSent(StaffReservationNotificationMail::class, function ($mail) use ($reservation, $projectName) {
        $mail->render();

        return $mail->hasTo('staff@example.com')
            && $mail->emailSubject === "Hotel Booking Cancelled: {$reservation->reservation_number} - {$reservation->hotel->name} - {$projectName}";
    });
});

it('does not email staff when no recipients are configured', function () {
    Mail::fake();

    $reservation = Reservation::factory()->paid()->create();
    configureHotelNotification($reservation, [
        'to' => [],
        'cc' => [],
        'bcc' => [],
    ]);

    (new SendStaffReservationNotificationJob($reservation->id, 'confirmed'))->handle();

    Mail::assertNotSent(StaffReservationNotificationMail::class);
});

it('dispatches the staff notification when a booking-received email is sent', function () {
    Mail::fake();
    Bus::fake([SendStaffReservationNotificationJob::class]);

    $reservation = Reservation::factory()->paid()->create();

    (new SendBookingReceivedJob($reservation->id))
        ->handle(app(ReservationService::class));

    Bus::assertDispatched(
        SendStaffReservationNotificationJob::class,
        fn ($job) => $job->reservationId === $reservation->id && $job->eventType === 'confirmed',
    );
});

it('dispatches the staff notification when a cancellation email is sent', function () {
    Mail::fake();
    Bus::fake([SendStaffReservationNotificationJob::class]);

    $reservation = Reservation::factory()->cancelled()->create();

    (new SendCancellationJob($reservation->id, 0.0))
        ->handle(app(ReservationService::class));

    Bus::assertDispatched(
        SendStaffReservationNotificationJob::class,
        fn ($job) => $job->reservationId === $reservation->id && $job->eventType === 'cancelled',
    );
});
