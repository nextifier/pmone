<?php

use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles + permissions from config so the `can:` middleware on the
    // logs routes resolves correctly (admin gets admin.logs, master gets all).
    $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
});

it('requires authentication to access logs', function () {
    $response = $this->getJson('/api/logs');

    $response->assertUnauthorized();
});

it('forbids users without the admin.logs permission from accessing logs', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertForbidden();
});

it('allows admin role to access logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('allows master role to access logs', function () {
    $user = User::factory()->create();
    $user->assignRole('master');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('can filter logs by log name', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?log_name=default');

    $response->assertSuccessful();
});

it('can filter logs by event', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?event=updated');

    $response->assertSuccessful();
});

it('can search logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?search=updated');

    $response->assertSuccessful();
});

it('can paginate logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?page=1&per_page=10');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('returns filter options for authorized users', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs/filter-options');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'log_names',
                'events',
                'causers',
            ],
        ]);
});

it('forbids users without the admin.logs permission from accessing filter options', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs/filter-options');

    $response->assertForbidden();
});

it('formats exported activity with a clear description and row count', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    activity()
        ->causedBy($user)
        ->event('exported')
        ->withProperties(['model_type' => 'PaymentTransaction', 'count' => 5])
        ->log('Exported payment transactions for xendit');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertSuccessful();

    $descriptions = collect($response->json('data'))->pluck('human_description');
    expect($descriptions)->toContain("{$user->name} exported payment transactions for xendit (5 rows)");
});

it('falls back to model type when an exported activity has no description', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    activity()
        ->causedBy($user)
        ->event('exported')
        ->withProperties(['model_type' => 'Contact'])
        ->log('');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $descriptions = collect($response->json('data'))->pluck('human_description');
    expect($descriptions)->toContain("{$user->name} exported Contact");
});

it('formats payment amounts as rupiah currency in descriptions', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    activity()
        ->event('payment_paid')
        ->withProperties(['amount' => 9952250])
        ->log('Payment received');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $descriptions = collect($response->json('data'))->pluck('human_description');
    expect($descriptions)->toContain('Payment received via Xendit (Rp9.952.250)');
});

it('formats currency fields in activity changes', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    activity()
        ->causedBy($user)
        ->event('updated')
        ->withProperties([
            'attributes' => ['total_amount' => 9952250, 'status' => 'confirmed'],
            'old' => ['total_amount' => 0, 'status' => 'pending_payment'],
        ])
        ->log('Updated reservation');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $changes = collect($response->json('data'))->firstWhere('event', 'updated')['changes'];
    $totalChange = collect($changes)->firstWhere('field', 'total amount');

    expect($totalChange['old'])->toBe('Rp0');
    expect($totalChange['new'])->toBe('Rp9.952.250');

    // Non-currency fields are left untouched (humanized client-side).
    $statusChange = collect($changes)->firstWhere('field', 'status');
    expect($statusChange['new'])->toBe('confirmed');
});

it('shows the promo code identifier and link in created activity', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $promoCode = PromoCode::factory()->create(['code' => 'TESTCODE99']);

    Sanctum::actingAs($user);

    $entry = collect($this->getJson('/api/logs')->json('data'))
        ->firstWhere('subject_type', 'PromoCode');

    expect($entry)->not->toBeNull();
    expect($entry['subject_name'])->toBe('TESTCODE99');
    expect($entry['human_description'])->toContain('"TESTCODE99"');
    expect($entry['subject_url'])->toBe("/promo-codes/{$promoCode->ulid}/show");
});

it('shows the reservation number in created activity', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $reservation = Reservation::factory()->create();

    Sanctum::actingAs($user);

    $entry = collect($this->getJson('/api/logs')->json('data'))
        ->firstWhere('subject_type', 'Reservation');

    expect($entry)->not->toBeNull();
    expect($entry['subject_name'])->toBe($reservation->reservation_number);
    expect($entry['human_description'])->toContain($reservation->reservation_number);
});

it('resolves payment gateway id to the project name and hides technical fields', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $reservation = Reservation::factory()->create();

    // payment_method (first key) and xendit_invoice_id (last key) are both hidden,
    // leaving only the middle field - exercises the re-indexing in getChangedFieldLabels.
    activity()
        ->performedOn($reservation)
        ->event('updated')
        ->withProperties([
            'attributes' => [
                'payment_method' => 'xendit',
                'payment_gateway_id' => 5,
                'xendit_invoice_id' => 'inv_abc',
            ],
            'old' => [
                'payment_method' => null,
                'payment_gateway_id' => null,
                'xendit_invoice_id' => null,
            ],
        ])
        ->log('Updated reservation');

    Sanctum::actingAs($user);

    $entry = collect($this->getJson('/api/logs')->json('data'))
        ->first(fn ($e) => $e['event'] === 'updated');

    $fields = collect($entry['changes'])->pluck('field');
    expect($fields)->toContain('payment gateway');
    expect($fields)->not->toContain('xendit invoice id');
    expect($fields)->not->toContain('payment method');

    $gatewayChange = collect($entry['changes'])->firstWhere('field', 'payment gateway');
    expect($gatewayChange['new'])->toBe($reservation->event->project->name);

    // Description must not crash when the only visible field is not at array index 0.
    expect($entry['human_description'])->toContain('payment gateway');
});

it('forbids admins without the admin.logs_clear permission from clearing logs', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/logs/clear');

    $response->assertForbidden();
});

it('allows master role to clear logs', function () {
    $user = User::factory()->create();
    $user->assignRole('master');

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/logs/clear');

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Logs cleared successfully',
        ]);

    // Verify activity logs table has a new clearing log entry
    expect(Activity::where('description', 'Activity logs cleared')->count())->toBe(1);
});

it('returns empty data when no logs match search', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs?search=nonexistentsearchterm12345');

    $response->assertSuccessful()
        ->assertJson([
            'data' => [],
            'meta' => [
                'total' => 0,
            ],
        ]);
});

it('includes human readable descriptions in response', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/logs');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'human_description',
                    'subject_name',
                    'time_ago',
                ],
            ],
        ]);

    // Check that human_description and subject_name are present
    $logs = $response->json('data');
    if (count($logs) > 0) {
        expect($logs[0]['human_description'])->toBeString();
        expect($logs[0]['time_ago'])->toBeString();
        // subject_name can be null or string
        expect($logs[0])->toHaveKey('subject_name');
    }
});
