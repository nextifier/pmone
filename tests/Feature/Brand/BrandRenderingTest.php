<?php

use App\Enums\Ticketing\PurchaseType;
use App\Mail\MagicLinkMail;
use App\Mail\Reservation\BookingReceivedMail;
use App\Mail\Reservation\CancellationMail;
use App\Mail\Reservation\HotelVoucherMail;
use App\Models\Event;
use App\Models\MagicLink;
use App\Models\Reservation;
use App\Support\EventIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every user-visible backend output must follow the deployment's brand
 * config instead of hardcoding "PM One". A fake brand proves it.
 */
beforeEach(function () {
    config([
        'app.name' => 'BrandX',
        'brand.support_email' => 'help@brandx.test',
        'brand.ics_domain' => 'brandx.test',
    ]);
});

it('renders the magic-link mail with the configured brand name', function () {
    $magicLink = new MagicLink([
        'email' => 'user@example.com',
        'token' => 'test-token',
        'expires_at' => now()->addMinutes(15),
    ]);

    $mail = new MagicLinkMail($magicLink);

    expect($mail->envelope()->subject)->toBe('Your BrandX Login Link');

    $html = $mail->render();

    expect($html)->toContain('BrandX')
        ->not->toContain('PM One');
});

it('renders reservation emails with brand fallbacks instead of PM One', function () {
    $reservation = Reservation::factory()->paid()->create();
    $reservation->hotel->update(['contact_email' => null]);
    $reservation->event->project->update(['email' => null]);
    $reservation = $reservation->fresh(['hotel', 'event.project']);

    $booking = (new BookingReceivedMail($reservation, 'https://example.test/magic'))->render();
    $voucher = (new HotelVoucherMail($reservation))->render();

    $cancelled = Reservation::factory()->cancelled()->create();
    $cancelled->hotel->update(['contact_email' => null]);
    $cancelled->event->project->update(['email' => null]);
    $cancellation = (new CancellationMail($cancelled->fresh(['hotel', 'event.project']), 0.0))->render();

    foreach ([$booking, $voucher, $cancellation] as $html) {
        expect($html)->not->toContain('PM One')
            ->not->toContain('support@pmone.id');
    }

    expect($booking)->toContain('help@brandx.test')
        ->and($voucher)->toContain('help@brandx.test');
});

it('brands ICS calendar output via config', function () {
    $event = Event::factory()->create([
        'start_date' => now()->addMonth()->setTime(9, 0),
        'end_date' => now()->addMonth()->addDay()->setTime(18, 0),
        'timezone' => 'Asia/Jakarta',
    ]);

    $ics = EventIcs::forEvent($event);

    expect($ics)->toContain('@brandx.test')
        ->toContain('PRODID:-//BrandX//Tickets//EN')
        ->not->toContain('pmone.id');
});

it('labels first-party purchases with the configured brand name', function () {
    expect(PurchaseType::FirstParty->label())->toBe('First-party (BrandX)');
});
