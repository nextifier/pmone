<?php

use App\Models\ApiConsumer;
use App\Models\Event;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\HotelTransferOption;
use App\Models\Project;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'is_active' => true]);
    $this->hotel = Hotel::factory()->withEvent($this->event)->create();
    $this->roomType = RoomType::factory()->create([
        'hotel_id' => $this->hotel->id,
        'base_rate' => 500000,
    ]);
    HotelEventAllotment::factory()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'quantity' => 5,
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'is_active' => true,
    ]);
});

function basePayload(array $overrides = []): array
{
    return array_merge([
        'hotel_id' => test()->hotel->id,
        'event_id' => test()->event->id,
        'guest_name' => 'Test Guest',
        'guest_email' => 'test@example.com',
        'guest_phone' => '+62812345678',
        'guest_identity_type' => 'nik',
        'guest_identity_number' => '3201010101010001',
        'items' => [[
            'room_type_id' => test()->roomType->id,
            'check_in_date' => now()->addDays(12)->toDateString(),
            'check_out_date' => now()->addDays(14)->toDateString(),
            'qty' => 1,
        ]],
        'accept_terms' => true,
    ], $overrides);
}

test('check_in_date in the past is rejected', function () {
    $payload = basePayload([
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'qty' => 1,
        ]],
    ]);

    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.check_in_date']);
});

test('check_in_date today is accepted', function () {
    $payload = basePayload([
        'items' => [[
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'qty' => 1,
        ]],
    ]);

    HotelEventAllotment::query()->update([
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);

    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);

    $response->assertJsonMissingValidationErrors(['items.0.check_in_date']);
});

test('transfer pax_count over max_pax is rejected', function () {
    $transfer = HotelTransferOption::factory()->create([
        'hotel_id' => $this->hotel->id,
        'max_pax' => 2,
        'price' => 200000,
        'direction' => 'in',
        'is_active' => true,
    ]);

    $payload = basePayload([
        'transfers' => [[
            'transfer_option_id' => $transfer->id,
            'direction' => 'in',
            'transfer_date' => now()->addDays(12)->toDateString(),
            'pax_count' => 5,
            'price' => 200000,
        ]],
    ]);

    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['transfers.0.pax_count']);
});

test('transfer belonging to different hotel is rejected', function () {
    $otherHotel = Hotel::factory()->withEvent($this->event)->create();
    $foreignTransfer = HotelTransferOption::factory()->create([
        'hotel_id' => $otherHotel->id,
        'max_pax' => 4,
        'price' => 200000,
        'direction' => 'in',
        'is_active' => true,
    ]);

    $payload = basePayload([
        'transfers' => [[
            'transfer_option_id' => $foreignTransfer->id,
            'direction' => 'in',
            'transfer_date' => now()->addDays(12)->toDateString(),
            'pax_count' => 1,
            'price' => 200000,
        ]],
    ]);

    $response = $this->postJson('/api/public/reservations', $payload, $this->headers);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['transfers.0.transfer_option_id']);
});
