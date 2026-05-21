<?php

use App\Exports\TransactionsExport;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'payment_gateways.read',
        'payment_gateways.view_balance',
        'payment_gateways.view_transactions',
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

    $this->txUrl = "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}/transactions";
});

function fakeTransactions(bool $hasMore = true): void
{
    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'has_more' => $hasMore,
            'data' => [
                [
                    'id' => 'txn_1',
                    'type' => 'PAYMENT',
                    'status' => 'SUCCESS',
                    'channel_code' => 'BCA',
                    'channel_category' => 'VIRTUAL_ACCOUNT',
                    'amount' => 250000,
                    'net_amount' => 245000,
                    'currency' => 'IDR',
                    'reference_id' => 'INV-1',
                    'created' => '2026-05-20T10:00:00Z',
                ],
                [
                    'id' => 'txn_2',
                    'type' => 'DISBURSEMENT',
                    'status' => 'PENDING',
                    'amount' => 100000,
                    'currency' => 'IDR',
                    'created' => '2026-05-19T10:00:00Z',
                ],
            ],
        ], 200),
    ]);
}

it('returns the transaction list from xendit', function () {
    fakeTransactions();

    $response = $this->getJson($this->txUrl);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'txn_1')
        ->assertJsonPath('data.0.type', 'PAYMENT')
        ->assertJsonPath('data.0.channel_code', 'BCA')
        ->assertJsonPath('data.1.status', 'PENDING');
});

it('exposes cursor pagination metadata', function () {
    fakeTransactions(hasMore: true);

    $this->getJson($this->txUrl)
        ->assertOk()
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonPath('meta.next_cursor', 'txn_2');
});

it('returns a null cursor when there are no more pages', function () {
    fakeTransactions(hasMore: false);

    $this->getJson($this->txUrl)
        ->assertOk()
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);
});

it('forwards the cursor and filters to xendit', function () {
    fakeTransactions();

    $this->getJson($this->txUrl.'?after_id=txn_99&type=payment&status=success')->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'after_id=txn_99')
            && str_contains($request->url(), 'types=PAYMENT')
            && str_contains($request->url(), 'statuses=SUCCESS');
    });
});

it('forbids users without the view_transactions permission', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($regular);

    $this->getJson($this->txUrl)->assertForbidden();
});

it('blocks transaction access for a gateway from another project', function () {
    $otherProject = Project::factory()->create();
    $otherGateway = ProjectPaymentGateway::factory()->for($otherProject)->create();

    $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$otherGateway->id}/transactions"
    )->assertNotFound();
});

it('maps a rejected secret key to a misconfigured error', function () {
    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'error_code' => 'INVALID_API_KEY',
            'message' => 'API key is invalid',
        ], 401),
    ]);

    $this->getJson($this->txUrl)
        ->assertStatus(502)
        ->assertJsonPath('error_code', 'PAYMENT_GATEWAY_MISCONFIGURED');
});

it('rejects an unknown transaction type', function () {
    Http::fake();

    $this->getJson($this->txUrl.'?type=bogus')->assertStatus(422);

    Http::assertNothingSent();
});

it('rejects a date range where date_to precedes date_from', function () {
    Http::fake();

    $this->getJson($this->txUrl.'?date_from=2026-05-20&date_to=2026-05-01')->assertStatus(422);
});

it('exposes the transactions capability on the gateway resource', function () {
    $response = $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}"
    );

    $response->assertOk();
    expect($response->json('data.capabilities'))->toContain('transactions');
});

// --- Export ---

it('exports transactions to an excel file', function () {
    Excel::fake();
    fakeTransactions(hasMore: false);
    $this->freezeTime();

    $expectedFilename = 'transactions_xendit_'.now()->format('Y-m-d_His').'.xlsx';

    $this->get($this->txUrl.'/export')->assertOk();

    Excel::assertDownloaded(
        $expectedFilename,
        fn (TransactionsExport $export) => $export->collection()->count() === 2,
    );
});

it('walks every cursor page when exporting', function () {
    Excel::fake();
    $this->freezeTime();
    Http::fake([
        'https://api.xendit.co/transactions*' => Http::sequence()
            ->push([
                'has_more' => true,
                'data' => [['id' => 'txn_1', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 100, 'currency' => 'IDR']],
            ])
            ->push([
                'has_more' => false,
                'data' => [['id' => 'txn_2', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 200, 'currency' => 'IDR']],
            ]),
    ]);

    $this->get($this->txUrl.'/export')->assertOk();

    Excel::assertDownloaded(
        'transactions_xendit_'.now()->format('Y-m-d_His').'.xlsx',
        fn (TransactionsExport $export) => $export->collection()->count() === 2,
    );
});

it('forbids exporting without the view_transactions permission', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($regular);

    $this->get($this->txUrl.'/export')->assertForbidden();
});
