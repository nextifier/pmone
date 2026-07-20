<?php

use App\Models\ApiConsumer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);

    $this->master = User::factory()->create();
    $this->master->assignRole('master');
});

it('reveals the current raw key and it still hashes to the stored auth hash', function () {
    $consumer = ApiConsumer::factory()->create();

    $response = $this->actingAs($this->master)
        ->getJson("/api/api-consumers/{$consumer->id}/reveal-key");

    $response->assertSuccessful();

    expect($response->json('key'))
        ->toStartWith('pk_')
        ->toBe($consumer->api_key)
        ->and(hash('sha256', $response->json('key')))->toBe($consumer->api_key_hash);
});

it('logs an api_key_revealed activity when the key is revealed', function () {
    $consumer = ApiConsumer::factory()->create();

    $this->actingAs($this->master)
        ->getJson("/api/api-consumers/{$consumer->id}/reveal-key")
        ->assertSuccessful();

    expect(
        Activity::where('event', 'api_key_revealed')
            ->where('subject_id', $consumer->id)
            ->exists()
    )->toBeTrue();
});

it('reveals the new key after a regenerate', function () {
    $consumer = ApiConsumer::factory()->create();

    $newKey = $this->actingAs($this->master)
        ->postJson("/api/api-consumers/{$consumer->id}/regenerate-key")
        ->json('key');

    $reveal = $this->actingAs($this->master)
        ->getJson("/api/api-consumers/{$consumer->id}/reveal-key");

    $reveal->assertSuccessful();
    expect($reveal->json('key'))->toBe($newKey);
});

it('forbids revealing for users without the update ability', function () {
    $consumer = ApiConsumer::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson("/api/api-consumers/{$consumer->id}/reveal-key")
        ->assertForbidden();
});

it('requires authentication to reveal the key', function () {
    $consumer = ApiConsumer::factory()->create();

    $this->getJson("/api/api-consumers/{$consumer->id}/reveal-key")
        ->assertUnauthorized();
});
