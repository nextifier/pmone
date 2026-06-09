<?php

use App\Jobs\Reservation\SendReservationWhatsAppJob;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.whatsapp.token', 'TEST_TOKEN');
    config()->set('services.whatsapp.phone_number_id', '123456789');
    config()->set('app.frontend_url', 'https://events.test');
});

it('sends a normalized whatsapp confirmation for a paid reservation', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

    $reservation = Reservation::factory()->paid()->create([
        'guest_name' => 'Budi',
        'guest_phone' => '08123456789',
        'reservation_number' => 'HTL-20260609-ABCD',
    ]);

    app()->call([new SendReservationWhatsAppJob($reservation->id), 'handle']);

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $params = array_column($body['template']['components'][0]['parameters'], 'text');

        return $body['to'] === '628123456789'
            && $body['template']['name'] === 'ticket_confirmation'
            && $params[0] === 'Budi'
            && $params[2] === 'HTL-20260609-ABCD'
            && str_starts_with($params[3], 'https://events.test/hotels/reservation/');
    });
});

it('skips sending when the guest phone is empty', function () {
    Http::fake();

    $reservation = Reservation::factory()->paid()->create(['guest_phone' => '']);

    app()->call([new SendReservationWhatsAppJob($reservation->id), 'handle']);

    Http::assertNothingSent();
});

it('does nothing when the reservation no longer exists', function () {
    Http::fake();

    app()->call([new SendReservationWhatsAppJob(999999), 'handle']);

    Http::assertNothingSent();
});

it('logs critically when the job permanently fails', function () {
    Log::spy();

    (new SendReservationWhatsAppJob(99))->failed(new RuntimeException('boom'));

    Log::shouldHaveReceived('critical')->once()->withArgs(
        fn ($message, $context) => str_contains($message, 'SendReservationWhatsAppJob failed permanently')
            && $context['reservation_id'] === 99,
    );
});
