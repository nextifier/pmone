<?php

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['reservations.read', 'reservations.manual_entry'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->event = Event::factory()->create();
    $this->hotel = Hotel::factory()->for($this->event)->create();
    $this->room = RoomType::factory()->create(['hotel_id' => $this->hotel->id, 'base_rate' => 1000000]);
});

test('admin can create reservation with skip_payment mode', function () {
    Queue::fake();

    $payload = [
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'Manual Guest',
        'guest_email' => 'manual@test.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '1234',
        'items' => [[
            'room_type_id' => $this->room->id,
            'check_in_date' => '2026-07-01',
            'check_out_date' => '2026-07-03',
            'qty' => 1,
        ]],
        'payment_mode' => 'skip',
    ];

    $response = $this->postJson("/api/events/{$this->event->id}/reservations/manual", $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'paid');

    $this->assertDatabaseHas('reservations', [
        'guest_email' => 'manual@test.com',
        'status' => ReservationStatus::Paid->value,
        'source' => 'admin_manual',
        'event_id' => $this->event->id,
    ]);
});

test('admin can create reservation with manual_paid mode', function () {
    Queue::fake();

    $payload = [
        'hotel_id' => $this->hotel->id,
        'guest_name' => 'Manual Bank',
        'guest_email' => 'bank@test.com',
        'guest_phone' => '0812',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '5678',
        'items' => [[
            'room_type_id' => $this->room->id,
            'check_in_date' => '2026-07-01',
            'check_out_date' => '2026-07-03',
            'qty' => 1,
        ]],
        'payment_mode' => 'manual_paid',
    ];

    $response = $this->postJson("/api/events/{$this->event->id}/reservations/manual", $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.payment.method', 'manual_bank_transfer');
});
