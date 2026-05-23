<?php

use App\Enums\Payment\CheckoutMethod;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\User;
use App\Services\Payment\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'payment_gateways.create',
        'payment_gateways.read',
        'payment_gateways.update',
        'payment_gateways.delete',
    ] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create(['status' => 'active']);
});

it('stores gateway and persists secret_key encrypted', function () {
    $payload = [
        'provider' => 'xendit',
        'label' => 'Production',
        'mode' => 'live',
        'secret_key' => 'xnd_live_SECRETVALUE12345',
        'webhook_token' => 'whtoken_TOPSECRET98765',
        'is_active' => true,
    ];

    $response = $this->postJson("/api/projects/{$this->project->username}/payment-gateways", $payload);

    $response->assertCreated()
        ->assertJsonPath('data.provider', 'xendit')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonMissing(['secret_key' => 'xnd_live_SECRETVALUE12345']);

    $row = DB::table('project_payment_gateways')->first();
    expect($row->secret_key)->not->toBe('xnd_live_SECRETVALUE12345');
    expect(Crypt::decryptString($row->secret_key))->toBe('xnd_live_SECRETVALUE12345');
    expect(Crypt::decryptString($row->webhook_token))->toBe('whtoken_TOPSECRET98765');
});

it('masks credentials in api response', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create([
        'secret_key' => 'xnd_live_VERYLONGSECRETKEY9999AB12',
        'webhook_token' => 'tokenABCDEF1234',
    ]);

    $response = $this->getJson("/api/projects/{$this->project->username}/payment-gateways/{$gateway->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.secret_key_masked', '••••••••AB12')
        ->assertJsonPath('data.webhook_token_masked', '••••••••1234')
        ->assertJsonPath('data.has_secret_key', true)
        ->assertJsonMissing(['secret_key' => 'xnd_live_VERYLONGSECRETKEY9999AB12'])
        ->assertJsonMissing(['webhook_token' => 'tokenABCDEF1234']);
});

it('listing gateways returns masked values only', function () {
    // Distinct mode+label so the (project, provider, mode, label) unique
    // constraint can never collide on the factory's random values.
    ProjectPaymentGateway::factory()->for($this->project)->create(['mode' => 'live', 'label' => 'Production']);
    ProjectPaymentGateway::factory()->for($this->project)->create(['mode' => 'test', 'label' => 'Sandbox']);

    $response = $this->getJson("/api/projects/{$this->project->username}/payment-gateways");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $row) {
        expect($row)->not->toHaveKey('secret_key');
        expect($row)->not->toHaveKey('webhook_token');
        expect($row['secret_key_masked'])->toStartWith('••••••••');
    }
});

it('keeps existing credentials when update payload omits them', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create([
        'secret_key' => 'xnd_initial_SECRET',
    ]);

    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$gateway->id}",
        ['label' => 'Renamed', 'secret_key' => '']
    );

    $response->assertSuccessful();
    expect($gateway->fresh()->secret_key)->toBe('xnd_initial_SECRET');
    expect($gateway->fresh()->label)->toBe('Renamed');
});

it('updating credentials replaces them and they remain encrypted at rest', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create([
        'secret_key' => 'xnd_old_SECRET',
    ]);

    $response = $this->patchJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$gateway->id}",
        ['secret_key' => 'xnd_NEW_SECRET']
    );

    $response->assertSuccessful();
    $row = DB::table('project_payment_gateways')->where('id', $gateway->id)->first();
    expect(Crypt::decryptString($row->secret_key))->toBe('xnd_NEW_SECRET');
});

it('only one active gateway per project', function () {
    $existing = ProjectPaymentGateway::factory()->for($this->project)->create([
        'mode' => 'live',
        'label' => 'Production',
        'is_active' => true,
    ]);

    $newGateway = ProjectPaymentGateway::factory()->for($this->project)->create([
        'mode' => 'test',
        'label' => 'Sandbox',
        'is_active' => true,
    ]);

    expect($existing->fresh()->is_active)->toBeFalse();
    expect($newGateway->fresh()->is_active)->toBeTrue();
});

it('does not affect gateways from other projects', function () {
    $otherProject = Project::factory()->create();

    $otherActive = ProjectPaymentGateway::factory()->for($otherProject)->create([
        'is_active' => true,
    ]);

    ProjectPaymentGateway::factory()->for($this->project)->create([
        'is_active' => true,
    ]);

    expect($otherActive->fresh()->is_active)->toBeTrue();
});

it('forbids users without permission', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($regular);

    $this->postJson("/api/projects/{$this->project->username}/payment-gateways", [
        'provider' => 'xendit',
        'mode' => 'live',
        'secret_key' => 'x',
    ])->assertForbidden();
});

it('blocks access to gateway from a different project', function () {
    $otherProject = Project::factory()->create();
    $gateway = ProjectPaymentGateway::factory()->for($otherProject)->create();

    $this->getJson("/api/projects/{$this->project->username}/payment-gateways/{$gateway->id}")
        ->assertNotFound();
});

it('resolver returns the active gateway for the requested mode', function () {
    ProjectPaymentGateway::factory()->for($this->project)->create([
        'mode' => 'test',
        'label' => 'Sandbox',
        'is_active' => true,
    ]);
    $expected = ProjectPaymentGateway::factory()->for($this->project)->create([
        'mode' => 'live',
        'label' => 'Production',
        'is_active' => true,
    ]);

    $resolver = app(PaymentGatewayResolver::class);
    $resolved = $resolver->resolve($this->project->fresh(), 'xendit', 'live');

    expect($resolved->id)->toBe($expected->id);
});

it('resolver throws when no active gateway exists', function () {
    ProjectPaymentGateway::factory()->for($this->project)->inactive()->create();

    $resolver = app(PaymentGatewayResolver::class);
    $resolver->resolve($this->project->fresh(), 'xendit');
})->throws(RuntimeException::class);

it('test-connection returns success when xendit accepts the key', function () {
    Http::fake([
        'https://api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true],
            ['channel_code' => 'BRI', 'is_activated' => true],
            ['channel_code' => 'OVO', 'is_activated' => false],
        ], 200),
    ]);

    $response = $this->postJson(
        "/api/projects/{$this->project->username}/payment-gateways/test-connection",
        [
            'provider' => 'xendit',
            'mode' => 'test',
            'secret_key' => 'xnd_test_validkey1234567890_ABCDEFG',
            'webhook_token' => 'whtoken_1234567890ABCDEF',
        ],
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('channels_count', 2)
        ->assertJsonPath('webhook_token.ok', true);
});

it('test-connection rejects malformed xendit secret key without calling provider', function () {
    Http::fake();

    $response = $this->postJson(
        "/api/projects/{$this->project->username}/payment-gateways/test-connection",
        [
            'provider' => 'xendit',
            'mode' => 'test',
            'secret_key' => 'not_an_xnd_prefixed_key_at_all',
        ],
    );

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'PAYMENT_GATEWAY_MISCONFIGURED');

    Http::assertNothingSent();
});

it('test-connection maps 401 to misconfigured', function () {
    Http::fake([
        'https://api.xendit.co/payment_channels' => Http::response([
            'error_code' => 'INVALID_API_KEY',
            'message' => 'API key is invalid',
        ], 401),
    ]);

    $response = $this->postJson(
        "/api/projects/{$this->project->username}/payment-gateways/test-connection",
        [
            'provider' => 'xendit',
            'mode' => 'test',
            'secret_key' => 'xnd_test_REVOKED_OR_TYPO_1234567890',
        ],
    );

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'PAYMENT_GATEWAY_MISCONFIGURED');
});

it('test-connection maps IP_NOT_ALLOWED to a specific error', function () {
    Http::fake([
        'https://api.xendit.co/payment_channels' => Http::response([
            'error_code' => 'IP_NOT_ALLOWED',
            'message' => 'IP address has not been added to the IP allowlist',
        ], 403),
    ]);

    $response = $this->postJson(
        "/api/projects/{$this->project->username}/payment-gateways/test-connection",
        [
            'provider' => 'xendit',
            'mode' => 'live',
            'secret_key' => 'xnd_live_VALIDKEYBUTBLOCKED_12345678',
        ],
    );

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'PAYMENT_GATEWAY_IP_NOT_ALLOWED');
});

it('test-connection requires authentication', function () {
    auth()->logout();

    $this->postJson(
        "/api/projects/{$this->project->username}/payment-gateways/test-connection",
        ['provider' => 'xendit', 'mode' => 'test', 'secret_key' => 'xnd_anything_at_all_12345']
    )->assertStatus(401);
});

it('test-connection flags short webhook tokens', function () {
    Http::fake([
        'https://api.xendit.co/payment_channels' => Http::response([], 200),
    ]);

    $response = $this->postJson(
        "/api/projects/{$this->project->username}/payment-gateways/test-connection",
        [
            'provider' => 'xendit',
            'mode' => 'test',
            'secret_key' => 'xnd_test_validkey1234567890_ABCDEFG',
            'webhook_token' => 'short',
        ],
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('webhook_token.ok', false);
});

it('stores a gateway with the requested checkout_method', function () {
    $response = $this->postJson("/api/projects/{$this->project->username}/payment-gateways", [
        'provider' => 'xendit',
        'mode' => 'test',
        'secret_key' => 'xnd_test_SECRETVALUE123456789',
        'checkout_method' => 'sessions_payment_link',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.checkout_method', 'sessions_payment_link');
});

it('defaults checkout_method to payment_link_legacy when omitted', function () {
    $response = $this->postJson("/api/projects/{$this->project->username}/payment-gateways", [
        'provider' => 'xendit',
        'mode' => 'test',
        'secret_key' => 'xnd_test_SECRETVALUE123456789',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.checkout_method', 'payment_link_legacy');
});

it('rejects an unknown checkout_method value', function () {
    $response = $this->postJson("/api/projects/{$this->project->username}/payment-gateways", [
        'provider' => 'xendit',
        'mode' => 'test',
        'secret_key' => 'xnd_test_SECRETVALUE123456789',
        'checkout_method' => 'totally_not_a_method',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('checkout_method');
});

it('can switch checkout_method via update', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->paymentLinkLegacy()->create();

    $this->patchJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$gateway->id}",
        ['checkout_method' => 'sessions_payment_link'],
    )->assertSuccessful()
        ->assertJsonPath('data.checkout_method', 'sessions_payment_link');

    expect($gateway->fresh()->checkout_method)->toBe(CheckoutMethod::SessionsPaymentLink);
});

it('exposes available_checkout_methods with all three options enabled', function () {
    $gateway = ProjectPaymentGateway::factory()->for($this->project)->create();

    $response = $this->getJson("/api/projects/{$this->project->username}/payment-gateways/{$gateway->id}");

    $response->assertSuccessful();

    $methods = collect($response->json('data.available_checkout_methods'));
    expect($methods)->toHaveCount(3);
    expect($methods->firstWhere('value', 'sessions_payment_link')['available'])->toBeTrue();
    expect($methods->firstWhere('value', 'sessions_components')['available'])->toBeTrue();
    expect($methods->firstWhere('value', 'payment_link_legacy')['available'])->toBeTrue();
});
