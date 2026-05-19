<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['reservations.read', 'reservations.upload_voucher', 'reservations.send_voucher'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

test('admin can upload voucher pdf', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id, 'event_id' => $event->id]);

    $pdfContent = "%PDF-1.4\n".str_repeat('a', 1024)."\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('voucher.pdf', $pdfContent);

    $response = $this->post("/api/events/{$event->id}/reservations/{$reservation->ulid}/voucher", [
        'voucher' => $file,
    ]);

    $response->assertSuccessful();

    expect($reservation->fresh()->hasMedia('voucher'))->toBeTrue();
});

test('admin cannot upload non-pdf-image file', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id, 'event_id' => $event->id]);

    $file = UploadedFile::fake()->create('voucher.txt', 100, 'text/plain');

    $response = $this->post("/api/events/{$event->id}/reservations/{$reservation->ulid}/voucher", [
        'voucher' => $file,
    ]);

    $response->assertStatus(422);
});

test('admin can upload voucher via tmp_voucher reference', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id, 'event_id' => $event->id]);

    $folder = 'tmp-'.uniqid();
    $filename = 'voucher.pdf';
    $pdfContent = "%PDF-1.4\n".str_repeat('a', 1024)."\n%%EOF";

    Storage::disk('local')->put("tmp/uploads/{$folder}/{$filename}", $pdfContent);
    Storage::disk('local')->put("tmp/uploads/{$folder}/metadata.json", json_encode([
        'original_name' => $filename,
        'mime_type' => 'application/pdf',
        'size' => strlen($pdfContent),
        'uploaded_at' => now()->toISOString(),
    ]));

    $response = $this->postJson("/api/events/{$event->id}/reservations/{$reservation->ulid}/voucher", [
        'tmp_voucher' => $folder,
    ]);

    $response->assertSuccessful();
    expect($reservation->fresh()->hasMedia('voucher'))->toBeTrue();
    expect(Storage::disk('local')->exists("tmp/uploads/{$folder}"))->toBeFalse();
});

test('upload voucher fails when neither file nor tmp_voucher provided', function () {
    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id, 'event_id' => $event->id]);

    $response = $this->postJson("/api/events/{$event->id}/reservations/{$reservation->ulid}/voucher", []);

    $response->assertStatus(422);
});

test('send voucher requires voucher to be uploaded first', function () {
    Queue::fake();

    $event = Event::factory()->create();
    $hotel = Hotel::factory()->withEvent($event)->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id, 'event_id' => $event->id]);

    $response = $this->postJson("/api/events/{$event->id}/reservations/{$reservation->ulid}/send-voucher");

    $response->assertStatus(422);
});
