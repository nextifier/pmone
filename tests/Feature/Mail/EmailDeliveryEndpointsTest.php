<?php

use App\Enums\EmailEventType;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['emails.view', 'emails.manage_suppressions'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->viewer = User::factory()->create(['email_verified_at' => now()]);
    $this->viewer->givePermissionTo('emails.view');

    $this->manager = User::factory()->create(['email_verified_at' => now()]);
    $this->manager->givePermissionTo(['emails.view', 'emails.manage_suppressions']);
});

it('refuses an unauthenticated request', function () {
    $this->getJson('/api/email-delivery/overview')->assertUnauthorized();
});

it('refuses a user without the emails.view permission', function () {
    $stranger = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($stranger)->getJson('/api/email-delivery/overview')->assertForbidden();
});

it('reports delivery counters and rates drawn from our own tables', function () {
    EmailMessage::factory()->count(2)->create();
    $delivered = EmailMessage::factory()->delivered()->create();
    EmailMessage::factory()->bounced()->create();
    EmailSuppression::factory()->create();

    // A delivered message that was also opened keeps its "delivery" status, so
    // the open can only be counted from the event log.
    EmailEvent::factory()->create([
        'message_id' => $delivered->message_id,
        'type' => EmailEventType::Open,
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($this->viewer)->getJson('/api/email-delivery/overview')->assertOk();

    $data = $response->json('data');

    expect($data['last_30_days']['sent'])->toBe(4)
        ->and($data['last_30_days']['delivered'])->toBe(1)
        ->and($data['last_30_days']['bounced'])->toBe(1)
        ->and($data['last_30_days']['opened'])->toBe(1)
        // JSON has no float/int distinction, so 25.0 arrives as 25.
        ->and($data['last_30_days']['bounce_rate'])->toEqual(25.0)
        // Opens are rated against what was delivered, not everything sent.
        ->and($data['last_30_days']['open_rate'])->toEqual(100.0)
        ->and($data['suppressed_total'])->toBe(1)
        ->and($data['daily'])->toHaveCount(30)
        ->and($data)->not->toHaveKey('quota');
});

it('lists messages newest first', function () {
    EmailMessage::factory()->create(['subject' => 'Older', 'sent_at' => now()->subDay()]);
    EmailMessage::factory()->create(['subject' => 'Newer', 'sent_at' => now()]);

    $response = $this->actingAs($this->viewer)->getJson('/api/email-delivery/messages')->assertOk();

    expect($response->json('data.0.subject'))->toBe('Newer');
});

it('finds a message by an exact recipient address', function () {
    EmailMessage::factory()->create(['recipients' => ['wanted@example.com']]);
    EmailMessage::factory()->create(['recipients' => ['other@example.com']]);

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/email-delivery/messages?search=wanted@example.com')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.recipients.0'))->toBe('wanted@example.com');
});

it('filters messages by status', function () {
    EmailMessage::factory()->create();
    EmailMessage::factory()->bounced()->create();

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/email-delivery/messages?status='.EmailEventType::Bounce->value)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('filters messages by several statuses at once', function () {
    EmailMessage::factory()->create();
    EmailMessage::factory()->delivered()->create();
    EmailMessage::factory()->bounced()->create();

    $statuses = EmailEventType::Bounce->value.','.EmailEventType::Delivery->value;

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/email-delivery/messages?status={$statuses}")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('sorts messages by the requested column and direction', function () {
    EmailMessage::factory()->create(['subject' => 'Older', 'sent_at' => now()->subDay()]);
    EmailMessage::factory()->create(['subject' => 'Newer', 'sent_at' => now()]);

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/email-delivery/messages?sort=sent_at')
        ->assertOk();

    expect($response->json('data.0.subject'))->toBe('Older');
});

it('ignores a sort column that is not allowlisted', function () {
    EmailMessage::factory()->create(['subject' => 'Older', 'sent_at' => now()->subDay()]);
    EmailMessage::factory()->create(['subject' => 'Newer', 'sent_at' => now()]);

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/email-delivery/messages?sort=message_id')
        ->assertOk();

    // Falls back to the default: newest first.
    expect($response->json('data.0.subject'))->toBe('Newer');
});

it('filters suppressions by several reasons at once', function () {
    EmailSuppression::factory()->create();
    EmailSuppression::factory()->complaint()->create();
    EmailSuppression::factory()->manual()->create();

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/email-delivery/suppressions?reason=complaint,manual')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('shows a message with its event timeline in order', function () {
    $message = EmailMessage::factory()->create();

    EmailEvent::factory()->create([
        'message_id' => $message->message_id,
        'type' => EmailEventType::Delivery,
        'occurred_at' => now()->addMinute(),
    ]);
    EmailEvent::factory()->create([
        'message_id' => $message->message_id,
        'type' => EmailEventType::Send,
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/email-delivery/messages/{$message->id}")
        ->assertOk();

    expect($response->json('data.events.0.type'))->toBe('send')
        ->and($response->json('data.events.1.type'))->toBe('delivery');
});

it('lists suppressions', function () {
    EmailSuppression::factory()->create(['email' => 'dead@example.com']);

    $response = $this->actingAs($this->viewer)->getJson('/api/email-delivery/suppressions')->assertOk();

    expect($response->json('data.0.email'))->toBe('dead@example.com')
        ->and($response->json('data.0.reason'))->toBe('bounce');
});

it('lets a manager remove an address from the suppression list', function () {
    $suppression = EmailSuppression::factory()->create();

    $this->actingAs($this->manager)
        ->deleteJson("/api/email-delivery/suppressions/{$suppression->id}")
        ->assertOk();

    expect(EmailSuppression::count())->toBe(0);
});

it('refuses to remove a suppression without the manage permission', function () {
    $suppression = EmailSuppression::factory()->create();

    $this->actingAs($this->viewer)
        ->deleteJson("/api/email-delivery/suppressions/{$suppression->id}")
        ->assertForbidden();

    expect(EmailSuppression::count())->toBe(1);
});
