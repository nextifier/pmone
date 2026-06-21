<?php

use App\Jobs\Ticket\SendAttendeeETicketJob;
use App\Mail\Ticket\AttendeeETicketMail;
use App\Models\Attendee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('serves an inline QR PNG for a valid attendee WITHOUT an API key', function () {
    // The email client fetches the <img> directly and cannot send X-API-Key,
    // so this route must live outside the api.key group.
    $attendee = Attendee::factory()->confirmed()->create();

    $response = $this->get("/api/public/attendees/{$attendee->ulid}/qr.png");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');

    $body = $response->getContent();
    expect($body)->not->toBeEmpty();
    expect(str_starts_with($body, "\x89PNG"))->toBeTrue();
});

it('404s the QR image for an unknown attendee', function () {
    $this->get('/api/public/attendees/NOPE/qr.png')->assertNotFound();
});

it('404s the QR image for a pending (unpaid) order', function () {
    // The QR is the gate key; an unpaid ticket has no usable code yet.
    $attendee = Attendee::factory()->create();

    $this->get("/api/public/attendees/{$attendee->ulid}/qr.png")->assertNotFound();
});

it('caches the generated QR bytes keyed by qr_token (no file on disk, no regen)', function () {
    $attendee = Attendee::factory()->confirmed()->create();

    expect(Cache::has("attendee-qr:{$attendee->qr_token}"))->toBeFalse();

    $this->get("/api/public/attendees/{$attendee->ulid}/qr.png")->assertOk();

    expect(Cache::has("attendee-qr:{$attendee->qr_token}"))->toBeTrue();
});

it('embeds the QR image as a plain <img> in the e-ticket email', function () {
    $attendee = Attendee::factory()->create();
    $qrUrl = route('public.attendees.qr-image', $attendee->ulid);

    $mailable = new AttendeeETicketMail(
        $attendee,
        'https://example.test/tickets/'.$attendee->ulid,
        null,
        null,
        $qrUrl,
    );

    $mailable->assertSeeInHtml('<img', false);
    $mailable->assertSeeInHtml($qrUrl, false);
});

it('passes the absolute QR image url to the e-ticket mailable when sending', function () {
    Mail::fake();

    $attendee = Attendee::factory()->create(['email' => 'holder@example.com']);

    SendAttendeeETicketJob::dispatchSync($attendee->id);

    Mail::assertSent(AttendeeETicketMail::class, function (AttendeeETicketMail $mail) use ($attendee) {
        return $mail->qrImageUrl === route('public.attendees.qr-image', $attendee->ulid);
    });
});
