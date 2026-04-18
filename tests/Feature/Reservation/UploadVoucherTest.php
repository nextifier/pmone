<?php

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id]);

    // Use real PDF header so the file isn't detected as application/x-empty
    $pdfContent = "%PDF-1.4\n".str_repeat('a', 1024)."\n%%EOF";
    $file = UploadedFile::fake()->createWithContent('voucher.pdf', $pdfContent);

    $response = $this->post("/api/reservations/{$reservation->ulid}/voucher", [
        'voucher' => $file,
    ]);

    $response->assertSuccessful();

    expect($reservation->fresh()->hasMedia('voucher'))->toBeTrue();
});

test('admin cannot upload non-pdf-image file', function () {
    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id]);

    $file = UploadedFile::fake()->create('voucher.txt', 100, 'text/plain');

    $response = $this->post("/api/reservations/{$reservation->ulid}/voucher", [
        'voucher' => $file,
    ]);

    $response->assertStatus(422);
});

test('send voucher requires voucher to be uploaded first', function () {
    Queue::fake();

    $hotel = Hotel::factory()->create();
    $reservation = Reservation::factory()->paid()->create(['hotel_id' => $hotel->id]);

    $response = $this->postJson("/api/reservations/{$reservation->ulid}/send-voucher");

    $response->assertStatus(422);
});
