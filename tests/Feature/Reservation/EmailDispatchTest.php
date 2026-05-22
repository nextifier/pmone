<?php

use App\Enums\ReservationStatus;
use App\Jobs\Reservation\SendBookingReceivedJob;
use App\Jobs\Reservation\SendCancellationJob;
use App\Jobs\Reservation\SendHotelVoucherJob;
use App\Mail\Reservation\BookingReceivedMail;
use App\Mail\Reservation\CancellationMail;
use App\Mail\Reservation\HotelVoucherMail;
use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->consumer = ApiConsumer::create([
        'name' => 'Test',
        'website_url' => 'https://test.com',
        'allowed_origins' => [],
        'rate_limit' => 1000,
        'is_active' => true,
    ]);
    $this->headers = ['X-API-Key' => $this->consumer->api_key];

    $this->project = Project::factory()->create(['status' => 'active']);
    ProjectPaymentGateway::factory()->for($this->project)->default()->create(['mode' => 'test']);
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->roomType = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_rate' => 1000000]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-10',
        'is_active' => true,
    ]);
});

test('public reservation with skip payment dispatches booking received job', function () {
    Queue::fake();

    foreach (['reservations.manual_entry'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo('reservations.manual_entry');
    $this->actingAs($admin);

    $response = $this->postJson("/api/events/{$this->event->id}/reservations/manual", [
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'X',
        'guest_email' => 'x@test.com',
        'guest_phone' => '08',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1',
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => '2026-06-02',
            'check_out_date' => '2026-06-04',
            'qty' => 1,
        ]],
        'payment_mode' => 'skip',
    ]);

    $response->assertStatus(201);

    Queue::assertPushed(SendBookingReceivedJob::class);
});

test('booking received email includes document links without rolling the magic token', function () {
    Mail::fake();

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_email' => 'guest@test.com',
    ]);
    $rawToken = app(ReservationService::class)->magicLinkTokenFor($reservation);
    $reservation->update(['magic_link_token' => hash('sha256', $rawToken)]);
    $tokenBefore = $reservation->fresh()->magic_link_token;

    app()->call([new SendBookingReceivedJob($reservation->id), 'handle']);

    // The token embedded in the Xendit success_url must survive the email job.
    expect($reservation->fresh()->magic_link_token)->toBe($tokenBefore);

    Mail::assertSent(BookingReceivedMail::class, function ($mail) use ($reservation) {
        return $mail->reservation->id === $reservation->id
            && str_contains((string) $mail->receiptUrl, '/receipt.pdf');
    });
});

test('deterministic magic token resolves via the public reservation endpoint', function () {
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);
    $rawToken = app(ReservationService::class)->magicLinkTokenFor($reservation);
    $reservation->update(['magic_link_token' => hash('sha256', $rawToken)]);

    $this->getJson("/api/public/reservations/magic/{$rawToken}", $this->headers)
        ->assertOk();
});

test('hotel voucher job sends HotelVoucherMail with a voucher download link', function () {
    Mail::fake();

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_email' => 'voucher@test.com',
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    file_put_contents($tmp, '%PDF-1.4 fake');
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    app()->call([new SendHotelVoucherJob($reservation->id), 'handle']);

    Mail::assertSent(HotelVoucherMail::class, fn ($mail) => str_contains((string) $mail->voucherUrl, '/voucher'));
});

test('voucher magic link downloads the e-voucher file without an API key', function () {
    Storage::fake('public');

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    file_put_contents($tmp, '%PDF-1.4 voucher contents');
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    $raw = Str::random(64);
    $reservation->update([
        'magic_link_token' => hash('sha256', $raw),
        'magic_link_expires_at' => now()->addYear(),
    ]);

    $this->get("/api/public/reservations/magic/{$raw}/voucher")->assertOk();
});

test('voucher magic link returns 404 when no voucher uploaded', function () {
    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $raw = Str::random(64);
    $reservation->update([
        'magic_link_token' => hash('sha256', $raw),
        'magic_link_expires_at' => now()->addYear(),
    ]);

    $this->get("/api/public/reservations/magic/{$raw}/voucher")->assertStatus(404);
});

test('hotel voucher attachment reads from the media disk', function () {
    Storage::fake('public');

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_email' => 'voucher@test.com',
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    $body = '%PDF-1.4 voucher contents';
    file_put_contents($tmp, $body);
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    $attachments = (new HotelVoucherMail($reservation->fresh()))->attachments();

    expect($attachments)->toHaveCount(1);

    $attachment = $attachments[0];
    $resolved = $attachment->attachWith(
        fn ($path) => ['kind' => 'path', 'value' => $path],
        fn ($data) => ['kind' => 'data', 'value' => $data()],
    );

    expect($resolved['kind'])->toBe('data')
        ->and($resolved['value'])->toBe($body)
        ->and($attachment->as)->toBe(basename($tmp))
        ->and($attachment->mime)->toBe('application/pdf');
});

test('cancellation job sends CancellationMail keeping the receipt link for paid bookings', function () {
    Mail::fake();

    $reservation = Reservation::factory()->cancelled()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_email' => 'cancel@test.com',
        'paid_at' => now(),
    ]);

    app()->call([new SendCancellationJob($reservation->id, 500000.0), 'handle']);

    Mail::assertSent(CancellationMail::class, function ($mail) {
        return str_contains((string) $mail->receiptUrl, '/receipt.pdf');
    });
});

test('send voucher endpoint dispatches job when voucher already uploaded', function () {
    Queue::fake();

    foreach (['reservations.read', 'reservations.send_voucher'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['reservations.read', 'reservations.send_voucher']);
    $this->actingAs($admin);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    file_put_contents($tmp, '%PDF-1.4 fake');
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    $response = $this->postJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/send-voucher");

    $response->assertSuccessful();
    Queue::assertPushed(SendHotelVoucherJob::class);
});

test('hotel voucher job skips sending when reservation is cancelled', function () {
    Mail::fake();

    $reservation = Reservation::factory()->cancelled()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_email' => 'voucher@test.com',
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    file_put_contents($tmp, '%PDF-1.4 fake');
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    app()->call([new SendHotelVoucherJob($reservation->id), 'handle']);

    Mail::assertNothingSent();
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('send voucher endpoint rejects when reservation is not paid', function () {
    Queue::fake();

    foreach (['reservations.read', 'reservations.send_voucher'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['reservations.read', 'reservations.send_voucher']);
    $this->actingAs($admin);

    $reservation = Reservation::factory()->cancelled()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    file_put_contents($tmp, '%PDF-1.4 fake');
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    $response = $this->postJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/send-voucher");

    $response->assertStatus(422);
    Queue::assertNotPushed(SendHotelVoucherJob::class);
});

test('send voucher endpoint allows resending after voucher already sent', function () {
    Queue::fake();

    foreach (['reservations.read', 'reservations.send_voucher'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['reservations.read', 'reservations.send_voucher']);
    $this->actingAs($admin);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'voucher_sent_at' => now()->subDay(),
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    file_put_contents($tmp, '%PDF-1.4 fake');
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    $response = $this->postJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/send-voucher");

    $response->assertSuccessful();
    Queue::assertPushed(SendHotelVoucherJob::class);
});

test('send voucher endpoint rate limits repeated sends', function () {
    Queue::fake();

    foreach (['reservations.read', 'reservations.send_voucher'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['reservations.read', 'reservations.send_voucher']);
    $this->actingAs($admin);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $tmp = tempnam(sys_get_temp_dir(), 'voucher_').'.pdf';
    file_put_contents($tmp, '%PDF-1.4 fake');
    $reservation->addMedia($tmp)->toMediaCollection('voucher');

    $url = "/api/events/{$this->event->id}/reservations/{$reservation->ulid}/send-voucher";

    $this->postJson($url)->assertSuccessful();

    $this->postJson($url)
        ->assertStatus(429)
        ->assertJsonStructure(['message', 'retry_after']);
});

test('send voucher endpoint rejects when no voucher uploaded yet', function () {
    foreach (['reservations.read', 'reservations.send_voucher'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->givePermissionTo(['reservations.read', 'reservations.send_voucher']);
    $this->actingAs($admin);

    $reservation = Reservation::factory()->paid()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
    ]);

    $response = $this->postJson("/api/events/{$this->event->id}/reservations/{$reservation->ulid}/send-voucher");

    $response->assertStatus(422);
});
