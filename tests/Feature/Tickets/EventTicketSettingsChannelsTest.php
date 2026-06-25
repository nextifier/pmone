<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('master');
    $this->actingAs($this->user);

    $this->project = Project::factory()->create();
    $this->event = Event::factory()->create(['project_id' => $this->project->id, 'tickets_enabled' => true]);
});

it('persists allowed payment channels into event settings and returns them', function () {
    $this->putJson("/api/events/{$this->event->id}/ticket-settings", [
        'allowed_payment_channels' => ['bca', 'qris'],
    ])->assertOk()
        ->assertJsonPath('data.allowed_payment_channels', ['BCA', 'QRIS']);

    expect($this->event->fresh()->settings['tickets']['allowed_payment_channels'])->toBe(['BCA', 'QRIS']);

    $this->getJson("/api/events/{$this->event->id}/ticket-settings")
        ->assertOk()
        ->assertJsonPath('data.allowed_payment_channels', ['BCA', 'QRIS']);
});

it('rejects unknown payment channel codes', function () {
    $this->putJson("/api/events/{$this->event->id}/ticket-settings", [
        'allowed_payment_channels' => ['BCA', 'FOO'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['allowed_payment_channels.1']);
});

it('clears the restriction with an empty array', function () {
    $this->event->update(['settings' => ['tickets' => ['allowed_payment_channels' => ['BCA']]]]);

    $this->putJson("/api/events/{$this->event->id}/ticket-settings", [
        'allowed_payment_channels' => [],
    ])->assertOk()
        ->assertJsonPath('data.allowed_payment_channels', []);

    expect($this->event->fresh()->settings['tickets']['allowed_payment_channels'])->toBe([]);
});

it('returns the catalog intersected with the gateway enabled channels', function () {
    ProjectPaymentGateway::factory()->create(['project_id' => $this->project->id, 'mode' => 'test', 'is_active' => true]);
    Http::fake([
        'api.xendit.co/payment_channels' => Http::response([
            ['channel_code' => 'BCA', 'is_activated' => true],
            ['channel_code' => 'QRIS', 'is_activated' => true],
            ['channel_code' => 'OVO', 'is_activated' => false],
        ], 200),
    ]);

    $response = $this->getJson("/api/events/{$this->event->id}/ticket-settings/payment-channels")
        ->assertOk()
        ->assertJsonPath('meta.gateway_configured', true);

    expect(collect($response->json('data'))->pluck('code')->all())
        ->toEqualCanonicalizing(['BCA', 'QRIS']);
});

it('returns the Midtrans-supported subset when the active gateway is Midtrans', function () {
    ProjectPaymentGateway::factory()->midtrans()->create([
        'project_id' => $this->project->id, 'mode' => 'test', 'is_active' => true,
    ]);

    $response = $this->getJson("/api/events/{$this->event->id}/ticket-settings/payment-channels")
        ->assertOk()
        ->assertJsonPath('meta.gateway_configured', true)
        ->assertJsonPath('meta.provider', 'midtrans');

    $codes = collect($response->json('data'))->pluck('code')->all();
    expect($codes)->toContain('BCA', 'GOPAY', 'QRIS', 'CREDIT_CARD')
        ->not->toContain('OVO', 'DANA', 'BJB');
});

it('falls back to the full catalog when no gateway is configured', function () {
    $project = Project::factory()->create(['hotel_reservation_enabled' => false]);
    $event = Event::factory()->withoutPaymentGateway()->create([
        'project_id' => $project->id,
        'tickets_enabled' => true,
    ]);
    expect($project->paymentGateways()->count())->toBe(0);

    $response = $this->getJson("/api/events/{$event->id}/ticket-settings/payment-channels")
        ->assertOk()
        ->assertJsonPath('meta.gateway_configured', false);

    expect(count($response->json('data')))->toBeGreaterThan(5);
});
