<?php

use App\Models\ApiConsumer;
use App\Models\Hotel;
use App\Models\Reservation;
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
});

test('magic link returns reservation when token matches', function () {
    $hotel = Hotel::factory()->create();
    $rawToken = bin2hex(random_bytes(24));

    $reservation = Reservation::factory()->create([
        'hotel_id' => $hotel->id,
        'magic_link_token' => hash('sha256', $rawToken),
    ]);

    $response = $this->getJson("/api/public/reservations/magic/{$rawToken}", $this->headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.reservation_number', $reservation->reservation_number);
});

test('magic link returns 404 for invalid token', function () {
    $response = $this->getJson('/api/public/reservations/magic/invalid-token', $this->headers);

    $response->assertNotFound();
});
