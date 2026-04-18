<?php

use App\Models\Event;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['hotels.read', 'hotels.create', 'hotels.update', 'hotels.delete'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $master = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $master->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);
});

test('hotel list is scoped to its event', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    Hotel::factory()->count(2)->for($eventA)->create();
    Hotel::factory()->count(3)->for($eventB)->create();

    $responseA = $this->getJson("/api/events/{$eventA->id}/hotels");
    $responseB = $this->getJson("/api/events/{$eventB->id}/hotels");

    expect($responseA->json('meta.total'))->toBe(2);
    expect($responseB->json('meta.total'))->toBe(3);
});

test('hotel from event A returns 404 when accessed via event B scope', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();
    $hotel = Hotel::factory()->for($eventA)->create();

    $response = $this->getJson("/api/events/{$eventB->id}/hotels/{$hotel->slug}");

    $response->assertNotFound();
});

test('same slug can coexist across different events', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    $hotelA = Hotel::factory()->for($eventA)->create(['slug' => 'grand-mercure', 'name' => 'Grand Mercure']);
    $hotelB = Hotel::factory()->for($eventB)->create(['slug' => 'grand-mercure', 'name' => 'Grand Mercure']);

    expect($hotelA->id)->not->toBe($hotelB->id);
    expect($hotelA->slug)->toBe($hotelB->slug);
});

test('hotel creation via nested route auto-assigns event_id from route', function () {
    $event = Event::factory()->create();

    $response = $this->postJson("/api/events/{$event->id}/hotels", [
        'name' => 'Nested Hotel',
        'city' => 'Bandung',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.event_id', $event->id);
});
