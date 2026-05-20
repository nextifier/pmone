<?php

use App\Models\ProjectPaymentGateway;
use App\Services\Xendit\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('returns only activated channels with known logo assets', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true, 'channel_category' => 'VIRTUAL_ACCOUNT'],
            ['channel_code' => 'BRI', 'is_activated' => false, 'channel_category' => 'VIRTUAL_ACCOUNT'],
            ['channel_code' => 'QRIS', 'is_activated' => true, 'channel_category' => 'QR_CODE'],
            ['channel_code' => 'UNKNOWN_CHANNEL', 'is_activated' => true, 'channel_category' => 'OTHER'],
        ], 200),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    $logos = XenditService::forGateway($gateway)->getEnabledPaymentChannels();

    $files = array_column($logos, 'file');
    expect($files)->toContain('bca.svg');
    expect($files)->toContain('qris.svg');
    expect($files)->not->toContain('bri.svg');
    expect($files)->toHaveCount(2);
});

test('caches the channel list for subsequent calls', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true],
        ], 200),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    XenditService::forGateway($gateway)->getEnabledPaymentChannels();
    XenditService::forGateway($gateway)->getEnabledPaymentChannels();
    XenditService::forGateway($gateway)->getEnabledPaymentChannels();

    Http::assertSentCount(1);
    expect(Cache::has(XenditService::PAYMENT_CHANNELS_CACHE_PREFIX."gateway:{$gateway->id}"))->toBeTrue();
});

test('falls back to a minimal logo set when the API call fails', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response('boom', 500),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    $logos = XenditService::forGateway($gateway)->getEnabledPaymentChannels();

    expect($logos)->not->toBeEmpty();
    $files = array_column($logos, 'file');
    expect($files)->toContain('visa.svg');
    expect($files)->toContain('bca.svg');
});

test('falls back to a minimal logo set when the API reports no activated channels', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => false],
        ], 200),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    $logos = XenditService::forGateway($gateway)->getEnabledPaymentChannels();

    // An invoice footer must never render empty - the absence of activated
    // channels must still yield the static fallback set.
    expect($logos)->not->toBeEmpty();
    expect(array_column($logos, 'file'))->toContain('visa.svg');
});

test('handles a data-wrapped payment_channels response', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            'data' => [
                ['channel_code' => 'GOPAY', 'is_activated' => true],
            ],
        ], 200),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    $logos = XenditService::forGateway($gateway)->getEnabledPaymentChannels();

    expect(array_column($logos, 'file'))->toContain('gopay.svg');
});

test('updating the gateway flushes the cache via observer', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true],
        ], 200),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    XenditService::forGateway($gateway)->getEnabledPaymentChannels();

    $cacheKey = XenditService::PAYMENT_CHANNELS_CACHE_PREFIX."gateway:{$gateway->id}";
    expect(Cache::has($cacheKey))->toBeTrue();

    $gateway->update(['label' => 'Renamed']);

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('artisan refresh command warms the cache for one gateway', function () {
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true],
            ['channel_code' => 'QRIS', 'is_activated' => true],
        ], 200),
    ]);

    $gateway = ProjectPaymentGateway::factory()->create();
    Cache::forget(XenditService::PAYMENT_CHANNELS_CACHE_PREFIX."gateway:{$gateway->id}");

    $this->artisan('xendit:refresh-payment-channels', ['--gateway' => $gateway->id])
        ->expectsOutputToContain("Refreshed gateway #{$gateway->id}")
        ->assertSuccessful();

    expect(Cache::has(XenditService::PAYMENT_CHANNELS_CACHE_PREFIX."gateway:{$gateway->id}"))->toBeTrue();
});
