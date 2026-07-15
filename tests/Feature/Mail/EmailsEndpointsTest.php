<?php

use App\Enums\EmailEventType;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Services\Resend\ResendEmailApi;
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
    $this->getJson('/api/emails/overview')->assertUnauthorized();
});

it('refuses a user without the emails.view permission', function () {
    $stranger = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($stranger)->getJson('/api/emails/overview')->assertForbidden();
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

    $response = $this->actingAs($this->viewer)->getJson('/api/emails/overview')->assertOk();

    $data = $response->json('data');

    expect($data['totals']['sent'])->toBe(4)
        ->and($data['totals']['delivered'])->toBe(1)
        ->and($data['totals']['bounced'])->toBe(1)
        ->and($data['totals']['opened'])->toBe(1)
        // JSON has no float/int distinction, so 25.0 arrives as 25.
        ->and($data['totals']['bounce_rate'])->toEqual(25.0)
        // Opens are rated against what was delivered, not everything sent.
        ->and($data['totals']['open_rate'])->toEqual(100.0)
        ->and($data['suppressed_total'])->toBe(1)
        ->and($data['daily'])->toHaveCount(30)
        ->and($data['range'])->toHaveKeys(['from', 'to']);
});

it('reports current sending usage against the configured plan limits', function () {
    config([
        'services.resend.limits.daily' => 100,
        'services.resend.limits.monthly' => 3000,
    ]);

    EmailMessage::factory()->create(['sent_at' => now()]);

    $usage = $this->actingAs($this->viewer)
        ->getJson('/api/emails/overview')
        ->assertOk()
        ->json('data.usage');

    expect($usage['daily']['limit'])->toBe(100)
        ->and($usage['monthly']['limit'])->toBe(3000)
        ->and($usage['daily']['used'])->toBe(1)
        ->and($usage['monthly']['used'])->toBe(1);
});

it('counts only messages sent within the requested date range', function () {
    EmailMessage::factory()->create(['sent_at' => now()->subDays(2)]);
    EmailMessage::factory()->create(['sent_at' => now()->subDays(20)]);

    $from = now()->subDays(5)->toDateString();
    $to = now()->toDateString();

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/emails/overview?date_from={$from}&date_to={$to}")
        ->assertOk();

    $data = $response->json('data');

    expect($data['totals']['sent'])->toBe(1)
        ->and($data['range']['from'])->toBe($from)
        ->and($data['range']['to'])->toBe($to)
        // The daily series spans the requested window inclusively (6 days).
        ->and($data['daily'])->toHaveCount(6);
});

it('lists messages newest first', function () {
    EmailMessage::factory()->create(['subject' => 'Older', 'sent_at' => now()->subDay()]);
    EmailMessage::factory()->create(['subject' => 'Newer', 'sent_at' => now()]);

    $response = $this->actingAs($this->viewer)->getJson('/api/emails/messages')->assertOk();

    expect($response->json('data.0.subject'))->toBe('Newer');
});

it('filters messages to the requested date range', function () {
    EmailMessage::factory()->create(['subject' => 'Recent', 'sent_at' => now()->subDay()]);
    EmailMessage::factory()->create(['subject' => 'Ancient', 'sent_at' => now()->subDays(40)]);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/emails/messages?date_from={$from}&date_to={$to}")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.subject'))->toBe('Recent');
});

it('finds a message by an exact recipient address', function () {
    EmailMessage::factory()->create(['recipients' => ['wanted@example.com']]);
    EmailMessage::factory()->create(['recipients' => ['other@example.com']]);

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/emails/messages?search=wanted@example.com')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.recipients.0'))->toBe('wanted@example.com');
});

it('filters messages by status', function () {
    EmailMessage::factory()->create();
    EmailMessage::factory()->bounced()->create();

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/emails/messages?status='.EmailEventType::Bounce->value)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('filters messages by several statuses at once', function () {
    EmailMessage::factory()->create();
    EmailMessage::factory()->delivered()->create();
    EmailMessage::factory()->bounced()->create();

    $statuses = EmailEventType::Bounce->value.','.EmailEventType::Delivery->value;

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/emails/messages?status={$statuses}")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('sorts messages by the requested column and direction', function () {
    EmailMessage::factory()->create(['subject' => 'Older', 'sent_at' => now()->subDay()]);
    EmailMessage::factory()->create(['subject' => 'Newer', 'sent_at' => now()]);

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/emails/messages?sort=sent_at')
        ->assertOk();

    expect($response->json('data.0.subject'))->toBe('Older');
});

it('ignores a sort column that is not allowlisted', function () {
    EmailMessage::factory()->create(['subject' => 'Older', 'sent_at' => now()->subDay()]);
    EmailMessage::factory()->create(['subject' => 'Newer', 'sent_at' => now()]);

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/emails/messages?sort=message_id')
        ->assertOk();

    // Falls back to the default: newest first.
    expect($response->json('data.0.subject'))->toBe('Newer');
});

it('filters suppressions by several reasons at once', function () {
    EmailSuppression::factory()->create();
    EmailSuppression::factory()->complaint()->create();
    EmailSuppression::factory()->manual()->create();

    $response = $this->actingAs($this->viewer)
        ->getJson('/api/emails/suppressions?reason=complaint,manual')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('shows a message with its event timeline in order, resolved by message id', function () {
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
        ->getJson("/api/emails/messages/{$message->message_id}")
        ->assertOk();

    expect($response->json('data.message_id'))->toBe($message->message_id)
        ->and($response->json('data.events.0.type'))->toBe('send')
        ->and($response->json('data.events.1.type'))->toBe('delivery');
});

it('returns the email body fetched from Resend', function () {
    $message = EmailMessage::factory()->create(['mailer' => 'resend']);

    $this->mock(ResendEmailApi::class, function ($mock) use ($message) {
        $mock->shouldReceive('get')
            ->once()
            ->with($message->message_id)
            ->andReturn([
                'html' => '<strong>Hi</strong>',
                'text' => 'Hi',
                'cc' => [],
                'bcc' => [],
                'reply_to' => [],
                'tags' => [],
                'last_event' => 'delivered',
                'scheduled_at' => null,
            ]);
    });

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/emails/messages/{$message->message_id}/content")
        ->assertOk();

    expect($response->json('data.available'))->toBeTrue()
        ->and($response->json('data.html'))->toBe('<strong>Hi</strong>')
        ->and($response->json('data.text'))->toBe('Hi');
});

it('reports the body as unavailable when Resend cannot return it', function () {
    $message = EmailMessage::factory()->create(['mailer' => 'resend']);

    $this->mock(ResendEmailApi::class, function ($mock) {
        $mock->shouldReceive('get')->once()->andThrow(new RuntimeException('not found'));
    });

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/emails/messages/{$message->message_id}/content")
        ->assertOk();

    expect($response->json('data.available'))->toBeFalse();
});

it('does not call Resend for a message sent through another mailer', function () {
    $message = EmailMessage::factory()->create(['mailer' => 'cloudflare']);

    $this->mock(ResendEmailApi::class, function ($mock) {
        $mock->shouldReceive('get')->never();
    });

    $response = $this->actingAs($this->viewer)
        ->getJson("/api/emails/messages/{$message->message_id}/content")
        ->assertOk();

    expect($response->json('data.available'))->toBeFalse();
});

it('lists suppressions', function () {
    EmailSuppression::factory()->create(['email' => 'dead@example.com']);

    $response = $this->actingAs($this->viewer)->getJson('/api/emails/suppressions')->assertOk();

    expect($response->json('data.0.email'))->toBe('dead@example.com')
        ->and($response->json('data.0.reason'))->toBe('bounce');
});

it('lets a manager remove an address from the suppression list', function () {
    $suppression = EmailSuppression::factory()->create();

    $this->actingAs($this->manager)
        ->deleteJson("/api/emails/suppressions/{$suppression->id}")
        ->assertOk();

    expect(EmailSuppression::count())->toBe(0);
});

it('refuses to remove a suppression without the manage permission', function () {
    $suppression = EmailSuppression::factory()->create();

    $this->actingAs($this->viewer)
        ->deleteJson("/api/emails/suppressions/{$suppression->id}")
        ->assertForbidden();

    expect(EmailSuppression::count())->toBe(1);
});
