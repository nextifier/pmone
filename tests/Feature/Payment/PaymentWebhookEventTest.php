<?php

use App\Models\PaymentWebhookEvent;
use App\Models\Project;
use App\Models\ProjectPaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach ([
        'payment_gateways.read',
        'payment_gateways.view_webhook_events',
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
});

// --- Middleware logging ---

it('logs an inbound xendit webhook with derived fields', function () {
    $this->postJson('/api/webhooks/xendit', [
        'external_id' => 'HTL-NONEXISTENT-1',
        'status' => 'PAID',
        'id' => 'inv_test_1',
    ]);

    $event = PaymentWebhookEvent::first();

    expect($event)->not->toBeNull();
    expect($event->provider)->toBe('xendit');
    expect($event->event_type)->toBe('invoice.paid');
    expect($event->external_id)->toBe('HTL-NONEXISTENT-1');
    expect($event->payload)->toHaveKey('external_id');
    expect($event->http_status)->not->toBeNull();
});

it('derives event type from the event field for refund webhooks', function () {
    $this->postJson('/api/webhooks/xendit', [
        'event' => 'refund.succeeded',
        'data' => ['invoice_id' => 'inv_refund_1'],
    ]);

    $event = PaymentWebhookEvent::first();

    expect($event->event_type)->toBe('refund.succeeded');
    expect($event->external_id)->toBe('inv_refund_1');
});

it('still returns the webhook response while logging is enabled', function () {
    $response = $this->postJson('/api/webhooks/xendit', [
        'external_id' => 'HTL-NONEXISTENT-2',
        'status' => 'PAID',
    ]);

    $response->assertSuccessful();
    expect(PaymentWebhookEvent::count())->toBe(1);
});

// --- Admin listing ---

it('lists webhook events for the gateway provider', function () {
    PaymentWebhookEvent::factory()->count(3)->create([
        'project_id' => $this->project->id,
        'provider' => 'xendit',
    ]);
    PaymentWebhookEvent::factory()->create(['provider' => 'xendit']); // other project

    $response = $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}/webhook-events"
    );

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters webhook events by status', function () {
    PaymentWebhookEvent::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'provider' => 'xendit',
        'status' => 'processed',
    ]);
    PaymentWebhookEvent::factory()->create([
        'project_id' => $this->project->id,
        'provider' => 'xendit',
        'status' => 'rejected',
    ]);

    $response = $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}/webhook-events?status=rejected"
    );

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('forbids users without the view_webhook_events permission', function () {
    $regular = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($regular);

    $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$this->gateway->id}/webhook-events"
    )->assertForbidden();
});

it('blocks webhook-event access for a gateway from another project', function () {
    $otherProject = Project::factory()->create();
    $otherGateway = ProjectPaymentGateway::factory()->for($otherProject)->create();

    $this->getJson(
        "/api/projects/{$this->project->username}/payment-gateways/{$otherGateway->id}/webhook-events"
    )->assertNotFound();
});
