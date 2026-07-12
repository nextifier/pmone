<?php

use App\Models\ApiConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('stores a sha256 hash of the raw key, never the raw key itself, in api_key_hash', function () {
    $consumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_known_raw_key',
    ]);

    expect($consumer->api_key_hash)
        ->toBe(hash('sha256', 'pk_test_known_raw_key'))
        ->toHaveLength(64)
        ->not->toBe('pk_test_known_raw_key');
});

it('authenticates a freshly created consumer by its raw key', function () {
    $consumer = ApiConsumer::factory()->create([
        'api_key' => 'pk_test_fresh_raw_key',
        'is_active' => true,
    ]);

    $found = ApiConsumer::byApiKey('pk_test_fresh_raw_key')->active()->first();

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($consumer->id);
});

it('does not authenticate a wrong key even when a consumer exists', function () {
    ApiConsumer::factory()->create(['api_key' => 'pk_test_real_key']);

    expect(ApiConsumer::byApiKey('pk_test_wrong_key')->first())->toBeNull();
});

it('authenticates a key that was backfilled from plaintext exactly the way the migration does it', function () {
    // Simulate a row exactly as the add_api_key_hash_to_api_consumers
    // migration leaves it after backfilling: api_key_hash computed from the
    // row's existing plaintext api_key via hash('sha256', $apiKey), with no
    // rotation of the raw value. This is the critical non-breaking
    // invariant: the 16 live sites keep sending their current raw key and
    // must still authenticate.
    $rawKey = 'pk_test_legacy_plaintext_key';

    $id = DB::table('api_consumers')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'name' => 'Legacy Consumer',
        'website_url' => 'https://legacy.example.com',
        'api_key' => $rawKey,
        'api_key_hash' => hash('sha256', $rawKey), // migration's backfill formula
        'rate_limit' => 60,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $found = ApiConsumer::byApiKey($rawKey)->active()->first();

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($id)
        ->and($found->api_key_hash)->toBe(hash('sha256', $rawKey))
        ->and($found->api_key_hash)->not->toBe($rawKey);
});

it('regenerates the api key, returns the new raw value once, and invalidates the old one', function () {
    $consumer = ApiConsumer::factory()->create(['api_key' => 'pk_test_old_key']);
    $oldHash = $consumer->api_key_hash;

    $newRawKey = $consumer->regenerateApiKey();

    expect($newRawKey)->not->toBe('pk_test_old_key')
        ->and($consumer->api_key_hash)->not->toBe($oldHash)
        ->and($consumer->api_key_hash)->toBe(hash('sha256', $newRawKey));

    expect(ApiConsumer::byApiKey('pk_test_old_key')->first())->toBeNull();
    expect(ApiConsumer::byApiKey($newRawKey)->first()?->id)->toBe($consumer->id);
});

it('never serializes api_key or api_key_hash even via a raw toArray call', function () {
    $consumer = ApiConsumer::factory()->create(['api_key' => 'pk_test_hidden_key']);

    $array = $consumer->toArray();

    expect($array)->not->toHaveKey('api_key')
        ->and($array)->not->toHaveKey('api_key_hash');
});
