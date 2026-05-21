<?php

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'payment_gateways.read',
        'payment_gateways.view_reconciliation',
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
    $this->event = Event::factory()->create(['project_id' => $this->project->id]);

    $this->reconUrl = "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}/reconciliation";
});

function makeReservation(string $number, ReservationStatus $status, float $total): Reservation
{
    return Reservation::factory()->create([
        'event_id' => test()->event->id,
        'reservation_number' => $number,
        'status' => $status,
        'total_amount' => $total,
    ]);
}

it('reconciles transactions against reservations and flags every discrepancy type', function () {
    makeReservation('HTL-MATCH-1', ReservationStatus::Paid, 500000);
    makeReservation('HTL-PENDING-1', ReservationStatus::PendingPayment, 300000);
    makeReservation('HTL-AMT-1', ReservationStatus::Paid, 999000);

    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'has_more' => false,
            'data' => [
                ['id' => 'txn_a', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 500000, 'currency' => 'IDR', 'reference_id' => 'HTL-MATCH-1'],
                ['id' => 'txn_b', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 300000, 'currency' => 'IDR', 'reference_id' => 'HTL-PENDING-1'],
                ['id' => 'txn_c', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 123000, 'currency' => 'IDR', 'reference_id' => 'HTL-AMT-1'],
                ['id' => 'txn_d', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 250000, 'currency' => 'IDR', 'reference_id' => 'HTL-ORPHAN-X'],
            ],
        ], 200),
    ]);

    $response = $this->getJson($this->reconUrl.'?date_from=2026-05-01&date_to=2026-05-31');

    $response->assertOk()
        ->assertJsonPath('data.transaction_count', 4)
        ->assertJsonPath('data.matched_count', 1)
        ->assertJsonPath('data.matched_amount', 500000)
        ->assertJsonPath('data.discrepancy_count', 3)
        ->assertJsonPath('data.truncated', false);

    $types = collect($response->json('data.discrepancies'))->pluck('type')->sort()->values()->all();
    expect($types)->toBe(['amount_mismatch', 'orphan', 'status_mismatch']);
});

it('reports a clean reconciliation when everything matches', function () {
    makeReservation('HTL-OK-1', ReservationStatus::Paid, 750000);

    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'has_more' => false,
            'data' => [
                ['id' => 'txn_ok', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 750000, 'currency' => 'IDR', 'reference_id' => 'HTL-OK-1'],
            ],
        ], 200),
    ]);

    $this->getJson($this->reconUrl.'?date_from=2026-05-01&date_to=2026-05-31')
        ->assertOk()
        ->assertJsonPath('data.matched_count', 1)
        ->assertJsonPath('data.discrepancy_count', 0);
});

it('only matches reservations from the same project', function () {
    $otherProject = Project::factory()->create();
    $otherEvent = Event::factory()->create(['project_id' => $otherProject->id]);
    Reservation::factory()->create([
        'event_id' => $otherEvent->id,
        'reservation_number' => 'HTL-OTHER-1',
        'status' => ReservationStatus::Paid,
        'total_amount' => 400000,
    ]);

    Http::fake([
        'https://api.xendit.co/transactions*' => Http::response([
            'has_more' => false,
            'data' => [
                ['id' => 'txn_x', 'type' => 'PAYMENT', 'status' => 'SUCCESS', 'amount' => 400000, 'currency' => 'IDR', 'reference_id' => 'HTL-OTHER-1'],
            ],
        ], 200),
    ]);

    $this->getJson($this->reconUrl.'?date_from=2026-05-01&date_to=2026-05-31')
        ->assertOk()
        ->assertJsonPath('data.matched_count', 0)
        ->assertJsonPath('data.discrepancies.0.type', 'orphan');
});

it('requires a date range', function () {
    Http::fake();

    $this->getJson($this->reconUrl)->assertStatus(422);
    Http::assertNothingSent();
});

it('forbids users without the view_reconciliation permission', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($regular);

    $this->getJson($this->reconUrl.'?date_from=2026-05-01&date_to=2026-05-31')->assertForbidden();
});

it('blocks reconciliation for a gateway from another project', function () {
    $otherProject = Project::factory()->create();
    $otherGateway = ProjectPaymentGateway::factory()->for($otherProject)->create();

    $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$otherGateway->id}/reconciliation?date_from=2026-05-01&date_to=2026-05-31"
    )->assertNotFound();
});
