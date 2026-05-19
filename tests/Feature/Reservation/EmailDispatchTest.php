<?php

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

test('booking received job sends BookingReceivedMail', function () {
    Mail::fake();

    $reservation = Reservation::factory()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_email' => 'guest@test.com',
    ]);

    (new SendBookingReceivedJob($reservation->id, 'rawtoken123'))->handle();

    Mail::assertSent(BookingReceivedMail::class, fn ($mail) => $mail->reservation->id === $reservation->id);
});

test('hotel voucher job sends HotelVoucherMail', function () {
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

    Mail::assertSent(HotelVoucherMail::class);
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

test('cancellation job sends CancellationMail', function () {
    Mail::fake();

    $reservation = Reservation::factory()->cancelled()->create([
        'hotel_id' => $this->hotel->id,
        'event_id' => $this->event->id,
        'guest_email' => 'cancel@test.com',
    ]);

    app()->call([new SendCancellationJob($reservation->id, 0.0), 'handle']);

    Mail::assertSent(CancellationMail::class);
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
