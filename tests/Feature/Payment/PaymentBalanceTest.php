<?php

use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'payment_gateways.view_balance',
    ] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $masterRole = Role::firstOrCreate(['name' => 'master', 'guard_name' => 'web']);
    $masterRole->syncPermissions(Permission::all());

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create(['status' => 'active']);
    $this->gateway = ProjectPaymentGateway::factory()->for($this->project)->create();

    $this->balanceUrl = "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}/balance";
});

it('returns the gateway balance from xendit', function () {
    Http::fake([
        'https://api.xendit.co/balance*' => Http::response(['balance' => 1500000], 200),
    ]);

    $response = $this->getJson($this->balanceUrl);

    $response->assertOk()
        ->assertJsonPath('data.available', 1500000)
        ->assertJsonPath('data.currency', 'IDR')
        ->assertJsonPath('data.accounts.0.account_type', 'CASH');

    expect($response->json('data.accounts'))->toHaveCount(2);
    expect($response->json('data.fetched_at'))->not->toBeNull();
});

it('caches the balance and does not re-hit xendit within the ttl', function () {
    Http::fake([
        'https://api.xendit.co/balance*' => Http::response(['balance' => 900000], 200),
    ]);

    $this->getJson($this->balanceUrl)->assertOk();
    Http::assertSentCount(2); // CASH + HOLDING

    $this->getJson($this->balanceUrl)->assertOk();
    Http::assertSentCount(2); // served from cache, no new calls
});

it('refresh=1 forces a live fetch', function () {
    Http::fake([
        'https://api.xendit.co/balance*' => Http::response(['balance' => 900000], 200),
    ]);

    $this->getJson($this->balanceUrl)->assertOk();
    Http::assertSentCount(2);

    $this->getJson($this->balanceUrl.'?refresh=1')->assertOk();
    Http::assertSentCount(4);
});

it('forbids users without the view_balance permission', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($regular);

    $this->getJson($this->balanceUrl)->assertForbidden();
});

it('blocks balance access for a gateway from another project', function () {
    $otherProject = Project::factory()->create();
    $otherGateway = ProjectPaymentGateway::factory()->for($otherProject)->create();

    $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$otherGateway->id}/balance"
    )->assertNotFound();
});

it('maps a rejected secret key to a misconfigured error', function () {
    Http::fake([
        'https://api.xendit.co/balance*' => Http::response([
            'error_code' => 'INVALID_API_KEY',
            'message' => 'API key is invalid',
        ], 401),
    ]);

    $this->getJson($this->balanceUrl)
        ->assertStatus(502)
        ->assertJsonPath('error_code', 'PAYMENT_GATEWAY_MISCONFIGURED');
});

it('maps an IP allowlist rejection to a specific error', function () {
    Http::fake([
        'https://api.xendit.co/balance*' => Http::response([
            'error_code' => 'IP_NOT_ALLOWED',
            'message' => 'IP address has not been added to the IP allowlist',
        ], 403),
    ]);

    $this->getJson($this->balanceUrl)
        ->assertStatus(502)
        ->assertJsonPath('error_code', 'PAYMENT_GATEWAY_IP_NOT_ALLOWED');
});

it('exposes provider capabilities on the gateway resource', function () {
    $response = $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}"
    );

    $response->assertOk();

    expect($response->json('data.capabilities'))
        ->toContain('balance')
        ->toContain('invoicing')
        ->toContain('refunds');
});
