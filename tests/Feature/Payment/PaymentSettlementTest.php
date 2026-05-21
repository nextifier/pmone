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
        'payment_gateways.read',
        'payment_gateways.view_settlement',
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

    $this->settlementUrl = "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}/settlement";
});

function fakeSettlementTransactions(): void
{
    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'has_more' => false,
            'data' => [
                ['id' => 't1', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 100000, 'currency' => 'IDR', 'settlement_status' => 'PENDING', 'estimated_settlement_time' => '2026-05-25T03:00:00Z'],
                ['id' => 't2', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 200000, 'currency' => 'IDR', 'settlement_status' => 'PENDING', 'estimated_settlement_time' => '2026-05-25T03:00:00Z'],
                ['id' => 't3', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 50000, 'currency' => 'IDR', 'settlement_status' => 'SETTLED'],
                ['id' => 't4', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 75000, 'currency' => 'IDR', 'settlement_status' => 'EARLY_SETTLED'],
            ],
        ], 200),
    ]);
}

it('summarizes pending and settled amounts', function () {
    fakeSettlementTransactions();

    $this->getJson($this->settlementUrl)
        ->assertOk()
        ->assertJsonPath('data.pending_amount', 300000)
        ->assertJsonPath('data.pending_count', 2)
        ->assertJsonPath('data.settled_amount', 125000)
        ->assertJsonPath('data.settled_count', 2);
});

it('buckets pending settlements by estimated date', function () {
    fakeSettlementTransactions();

    $this->getJson($this->settlementUrl)
        ->assertOk()
        ->assertJsonCount(1, 'data.upcoming')
        ->assertJsonPath('data.upcoming.0.date', '2026-05-25')
        ->assertJsonPath('data.upcoming.0.amount', 300000)
        ->assertJsonPath('data.upcoming.0.count', 2);
});

it('forbids users without the view_settlement permission', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($regular);

    $this->getJson($this->settlementUrl)->assertForbidden();
});

it('blocks settlement access for a gateway from another project', function () {
    $otherProject = Project::factory()->create();
    $otherGateway = ProjectPaymentGateway::factory()->for($otherProject)->create();

    $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$otherGateway->id}/settlement"
    )->assertNotFound();
});

it('exposes the settlement capability on the gateway resource', function () {
    $response = $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}"
    );

    $response->assertOk();
    expect($response->json('data.capabilities'))->toContain('settlement');
});
